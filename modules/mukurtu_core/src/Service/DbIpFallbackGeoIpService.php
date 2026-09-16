<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\visitors_geoip\VisitorsGeoIpInterface;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\GeoIp2Exception;

/**
 * Falls back to a bundled, license-free DB-IP database when MaxMind has none.
 *
 * visitors_geoip's own GeoIpService only ever resolves a visit's precise
 * coordinates from a MaxMind GeoLite2-City/GeoIP2-City database, which needs
 * a (free, but separately registered) MaxMind license key most sites never
 * set up -- see docs/visitors-geoip-setup.md. Without one, every visit falls
 * back to guessing a country from the browser's Accept-Language header
 * alone, with no coordinates at all.
 *
 * DB-IP publishes a free "City Lite" database, updated monthly, under a
 * genuinely redistributable CC BY 4.0 license (unlike MaxMind's GeoLite2,
 * whose EULA prohibits third-party redistribution outright), in the same
 * MMDB format GeoIp2\Database\Reader already reads. mukurtu_core downloads
 * it automatically (see DbIpDownloadService) so a site gets working, real
 * geolocation with no registration step at all. A site that configures its
 * own MaxMind key still gets MaxMind's better precision: this decorator
 * only reaches for the DB-IP file when the real service (that is, MaxMind)
 * has nothing.
 *
 * Attribution: IP Geolocation by DB-IP (https://db-ip.com).
 *
 * @see \Drupal\mukurtu_core\Service\DbIpDownloadService
 * @see docs/visitors-geoip-setup.md
 */
final class DbIpFallbackGeoIpService implements VisitorsGeoIpInterface {

  /**
   * The filename DbIpDownloadService saves the database under.
   */
  public const FILENAME = 'dbip-city-lite.mmdb';

  /**
   * The lazily-constructed DB-IP reader, or FALSE once known unavailable.
   *
   * @var \GeoIp2\Database\Reader|false|null
   */
  private Reader|false|null $dbIpReader = NULL;

  /**
   * Constructs the fallback geoip service.
   *
   * @param \Drupal\visitors_geoip\VisitorsGeoIpInterface $inner
   *   The decorated visitors_geoip.lookup service (MaxMind-backed).
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   */
  public function __construct(
    private readonly VisitorsGeoIpInterface $inner,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function metadata() {
    return $this->inner->metadata() ?? $this->dbIpReader()?->metadata();
  }

  /**
   * {@inheritdoc}
   */
  public function city($ip_address) {
    $record = $this->inner->city($ip_address);
    if ($record) {
      return $record;
    }

    $reader = $this->dbIpReader();
    if (!$reader) {
      return NULL;
    }

    try {
      return $reader->city($ip_address);
    }
    catch (GeoIp2Exception | \InvalidArgumentException $e) {
      // GeoIp2\Database\Reader::city() throws AddressNotFoundException for
      // any address the database has no record for -- private/loopback
      // ranges (routine on any dev site, and not unheard of in production
      // behind some proxy setups) included -- and \InvalidArgumentException
      // for a malformed address string. Neither visitors_geoip's own
      // GeoIpService nor its caller, Visitors::doLocation(), catches either
      // one, so a site with MaxMind configured already relies on this
      // exception simply never being hit in practice, which real per-visit
      // traffic does not guarantee. Not this class's bug to fix, but
      // swallowing it here at least keeps the DB-IP fallback from crashing a
      // hit that the MaxMind path would have crashed on too.
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReader() {
    return $this->inner->getReader() ?: $this->dbIpReader();
  }

  /**
   * {@inheritdoc}
   */
  public function setReader($reader) {
    $this->inner->setReader($reader);
  }

  /**
   * {@inheritdoc}
   */
  public function hasLibrary($class_name = 'GeoIp2\Database\Reader'): bool {
    return $this->inner->hasLibrary($class_name);
  }

  /**
   * {@inheritdoc}
   */
  public function hasExtension($extension = 'maxminddb'): bool {
    return $this->inner->hasExtension($extension);
  }

  /**
   * Returns a reader for the bundled DB-IP database, if one is present.
   */
  private function dbIpReader(): ?Reader {
    if ($this->dbIpReader === NULL) {
      $geoip_path = $this->configFactory->get('visitors_geoip.settings')->get('geoip_path') ?? '';
      $real_path = $this->fileSystem->realpath(rtrim((string) $geoip_path, '/') . '/' . self::FILENAME);
      // realpath() on a missing file returns FALSE; only construct a Reader
      // once, and remember there was nothing to read rather than re-checking
      // the filesystem on every single visit this process handles.
      $this->dbIpReader = $real_path ? new Reader($real_path) : FALSE;
    }

    return $this->dbIpReader ?: NULL;
  }

}
