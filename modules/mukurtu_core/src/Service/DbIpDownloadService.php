<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Downloads DB-IP's free "City Lite" database as a MaxMind fallback.
 *
 * @see \Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService
 * @see docs/visitors-geoip-setup.md
 */
class DbIpDownloadService {

  /**
   * DB-IP publishes one file per calendar month at this URL prefix.
   */
  private const URL_PREFIX = 'https://download.db-ip.com/free/dbip-city-lite-';

  /**
   * How long a downloaded database is trusted before a refresh is due.
   *
   * DB-IP republishes monthly; a little over a month gives a cron-triggered
   * check (which does not run to the day) room to succeed on its first try
   * after a new file is published, rather than needing a second cron cycle.
   */
  private const MAX_AGE_DAYS = 35;

  /**
   * Constructs the download service.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The mukurtu_core logger channel.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileSystemInterface $fileSystem,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Whether the bundled database is missing or old enough to refresh.
   */
  public function isStale(): bool {
    $path = $this->destinationRealPath();
    if (!$path) {
      return TRUE;
    }

    $modified = @filemtime($path);
    return $modified === FALSE || (time() - $modified) > (self::MAX_AGE_DAYS * 86400);
  }

  /**
   * Downloads and installs the current (or most recent) monthly database.
   *
   * Safe to call unconditionally: failures are logged, not thrown, since
   * this runs from contexts -- cron, module install -- that must not fail a
   * page request or a site install over a network hiccup or DB-IP being
   * temporarily unreachable. A site can always retry via
   * `drush mukurtu:geoip:download-dbip`.
   *
   * @return bool
   *   TRUE if a database was downloaded and installed.
   */
  public function download(): bool {
    $geoip_path = (string) ($this->configFactory->get('visitors_geoip.settings')->get('geoip_path') ?? '');
    $directory = $this->fileSystem->realpath(rtrim($geoip_path, '/'));
    if (!$directory) {
      $this->logger->warning('Cannot download the DB-IP fallback geolocation database: the configured GeoIP path (%path, see /admin/config/system/visitors/geoip) does not exist or is not accessible.', ['%path' => $geoip_path]);
      return FALSE;
    }

    foreach ($this->candidateMonths() as $month) {
      $gz_path = $this->fetch(self::URL_PREFIX . $month . '.mmdb.gz');
      if ($gz_path === NULL) {
        continue;
      }

      $installed = $this->installDecompressed($gz_path, $directory . '/' . DbIpFallbackGeoIpService::FILENAME);
      @unlink($gz_path);

      if ($installed) {
        return TRUE;
      }
    }

    $this->logger->warning('Could not download a DB-IP fallback geolocation database for the current or previous month. Visitor locations will keep relying on browser-language guesses (or MaxMind, if configured) until this succeeds.');
    return FALSE;
  }

  /**
   * The current calendar month, then the previous one, both UTC.
   *
   * DB-IP typically publishes a few days into the month; trying the
   * previous month too covers that window without needing to know exactly
   * when this month's file goes live.
   *
   * @return string[]
   *   Months formatted "Y-m".
   */
  private function candidateMonths(): array {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    return [$now->format('Y-m'), $now->modify('-1 month')->format('Y-m')];
  }

  /**
   * Downloads one URL to a temporary file.
   *
   * @return string|null
   *   A real path to the downloaded file, or NULL if the request failed
   *   (including a 404 for a month DB-IP has not published yet).
   */
  private function fetch(string $url): ?string {
    $temp_uri = $this->fileSystem->tempnam('temporary://', 'dbip_');
    $temp_path = $this->fileSystem->realpath($temp_uri);

    try {
      $this->httpClient->request('GET', $url, ['sink' => $temp_path]);
      return $temp_path;
    }
    catch (GuzzleException $e) {
      @unlink($temp_path);
      return NULL;
    }
  }

  /**
   * Streams a gzip-compressed file to its decompressed destination.
   *
   * Streamed rather than read into memory at once: the decompressed
   * database is well over 100MB, more than comfortable to hold twice over
   * (compressed and decompressed) in a PHP process's memory limit.
   *
   * Writes to a temporary path alongside the destination first and renames
   * it into place, so a request racing this one (or a crashed download)
   * never sees a half-written database file.
   */
  private function installDecompressed(string $gz_path, string $destination): bool {
    $gz = @gzopen($gz_path, 'rb');
    if ($gz === FALSE) {
      $this->logger->warning('Downloaded DB-IP database at %path was not a readable gzip file.', ['%path' => $gz_path]);
      return FALSE;
    }

    // Suffixed with a unique id, not just '.tmp': cron and a manually-run
    // drush command could in principle overlap, and two downloads writing
    // through the same temp path at once would corrupt both.
    $temp_destination = $destination . '.' . uniqid() . '.tmp';
    $out = @fopen($temp_destination, 'wb');
    if ($out === FALSE) {
      gzclose($gz);
      $this->logger->warning('Could not open %path for writing.', ['%path' => $temp_destination]);
      return FALSE;
    }

    while (!gzeof($gz)) {
      fwrite($out, gzread($gz, 1024 * 512));
    }
    gzclose($gz);
    fclose($out);

    // A monthly City Lite file has run well over 100MB for years; anything
    // far short of that is a truncated download or an unexpected response
    // body (an error page saved as if it were the file, say), not a usable
    // database. Cheap insurance since DB-IP does not publish a checksum for
    // the free tier to verify against instead.
    if (filesize($temp_destination) < 10 * 1024 * 1024) {
      $this->logger->warning('Downloaded DB-IP database at %path is implausibly small (%size bytes); discarding it rather than installing a likely-truncated file.', [
        '%path' => $temp_destination,
        '%size' => filesize($temp_destination),
      ]);
      @unlink($temp_destination);
      return FALSE;
    }

    if (!@rename($temp_destination, $destination)) {
      @unlink($temp_destination);
      $this->logger->warning('Could not move the downloaded DB-IP database into place at %path.', ['%path' => $destination]);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * The real path to where the database would already be, if present.
   */
  private function destinationRealPath(): ?string {
    $geoip_path = (string) ($this->configFactory->get('visitors_geoip.settings')->get('geoip_path') ?? '');
    $path = $this->fileSystem->realpath(rtrim($geoip_path, '/') . '/' . DbIpFallbackGeoIpService::FILENAME);
    return $path ?: NULL;
  }

}
