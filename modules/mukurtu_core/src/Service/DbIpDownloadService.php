<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Service;

use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Downloads DB-IP's free "City Lite" database as a MaxMind fallback.
 *
 * @see \Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService
 * @see \Drupal\mukurtu_core\Service\DbIpDatabaseLocator
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
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\mukurtu_core\Service\DbIpDatabaseLocator $locator
   *   Resolves where the database is stored.
   * @param \Psr\Log\LoggerInterface $logger
   *   The mukurtu_core logger channel.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly FileSystemInterface $fileSystem,
    private readonly DbIpDatabaseLocator $locator,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Whether the bundled database is missing or old enough to refresh.
   */
  public function isStale(): bool {
    $path = $this->locator->realpath();
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
   * this runs from contexts -- cron, module install, an update hook -- that
   * must not fail a page request, a site install or an update run over a
   * network hiccup or DB-IP being temporarily unreachable. A site can
   * always retry via `drush mukurtu:geoip:download-dbip`.
   *
   * @return bool
   *   TRUE if a database was downloaded and installed.
   */
  public function download(): bool {
    foreach ($this->candidateMonths() as $month) {
      $gz_path = $this->fetch(self::URL_PREFIX . $month . '.mmdb.gz');
      if ($gz_path === NULL) {
        continue;
      }

      // One download, tried against every storage candidate in order --
      // not one download per candidate: a month that fails to fetch at all
      // should not be retried per-candidate, and a candidate whose
      // directory cannot be prepared should not stop the next candidate
      // (or the next month) from being tried.
      foreach ($this->locator->candidateUris() as $destination) {
        $directory = dirname($destination);
        if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
          $this->logger->notice('Cannot prepare %directory for the DB-IP fallback database; trying the next storage location.', ['%directory' => $directory]);
          continue;
        }

        if ($this->installDecompressed($gz_path, $destination)) {
          @unlink($gz_path);
          return TRUE;
        }
      }

      @unlink($gz_path);
    }

    $this->logger->warning('Could not download a DB-IP fallback geolocation database for the current or previous month, at any available storage location. Visitor locations will keep relying on browser-language guesses (or MaxMind, if configured) until this succeeds.');
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
      $this->logger->notice('DB-IP fallback database fetch of %url failed: %message', ['%url' => $url, '%message' => $e->getMessage()]);
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
   * Writes to a temporary URI alongside the destination first and moves it
   * into place, so a request racing this one (or a crashed download) never
   * sees a half-written database file.
   *
   * @param string $gz_path
   *   A real filesystem path to the downloaded gzip file.
   * @param string $destination
   *   A stream-wrapper URI (private:// or public://), not a plain path --
   *   this is what makes the write land somewhere the process actually has
   *   permission to write, on hosting that runs the codebase and PHP as
   *   different users. gzopen() and fopen() both handle stream-wrapper URIs
   *   transparently.
   */
  private function installDecompressed(string $gz_path, string $destination): bool {
    $gz = @gzopen($gz_path, 'rb');
    if ($gz === FALSE) {
      $this->logger->warning('Downloaded DB-IP database at %path was not a readable gzip file.', ['%path' => $gz_path]);
      return FALSE;
    }

    // Suffixed with a unique id, not just '.tmp': cron, a manually-run
    // drush command and an update hook could in principle overlap, and two
    // downloads writing through the same temp URI at once would corrupt
    // both.
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
    $size = filesize($temp_destination);
    if ($size < 10 * 1024 * 1024) {
      $this->logger->warning('Downloaded DB-IP database at %path is implausibly small (%size bytes); discarding it rather than installing a likely-truncated file.', [
        '%path' => $temp_destination,
        '%size' => $size,
      ]);
      $this->fileSystem->delete($temp_destination);
      return FALSE;
    }

    try {
      $this->fileSystem->move($temp_destination, $destination, FileExists::Replace);
    }
    catch (FileException $e) {
      $this->fileSystem->delete($temp_destination);
      $this->logger->warning('Could not move the downloaded DB-IP database into place at %path: %message', ['%path' => $destination, '%message' => $e->getMessage()]);
      return FALSE;
    }

    return TRUE;
  }

}
