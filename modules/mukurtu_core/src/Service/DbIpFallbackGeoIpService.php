<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Service;

use Drupal\visitors_geoip\VisitorsGeoIpInterface;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\GeoIp2Exception;
use GeoIp2\Model\City;

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
   * @param \Drupal\mukurtu_core\Service\DbIpDatabaseLocator $locator
   *   Resolves where the DB-IP database is stored.
   */
  public function __construct(
    private readonly VisitorsGeoIpInterface $inner,
    private readonly DbIpDatabaseLocator $locator,
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
    // GeoIp2\Database\Reader::city() throws AddressNotFoundException for any
    // address the database has no record for -- private/loopback ranges
    // (routine on any dev site, and not unheard of in production behind some
    // proxy setups) included -- and \InvalidArgumentException for a
    // malformed address string. Neither visitors_geoip's own GeoIpService
    // nor its caller, Visitors::doLocation(), catches either one
    // (https://www.drupal.org/i/3618588), so an uncaught one fails the whole
    // tracking request and the hit is never recorded. Caught for both the
    // inner (MaxMind) lookup and the DB-IP one.
    try {
      $record = $this->inner->city($ip_address);
    }
    catch (GeoIp2Exception | \InvalidArgumentException $e) {
      $record = NULL;
    }
    if ($record) {
      return static::normalize($record);
    }

    $reader = $this->dbIpReader();
    if (!$reader) {
      return NULL;
    }

    try {
      return static::normalize($reader->city($ip_address));
    }
    catch (GeoIp2Exception | \InvalidArgumentException $e) {
      return NULL;
    }
  }

  /**
   * Fills in the record fields visitors_geoip writes to NOT NULL columns.
   *
   * Visitors::doLocation() and RebuildLocationService::rebuild() copy
   * continent->code, country->isoCode, subdivisions[0]->isoCode and
   * city->names['en'] straight into the visitors table, whose columns for
   * them are all NOT NULL. Any of the four can be missing from a record, and
   * one always is with DB-IP: its City Lite database has subdivision names
   * but no subdivision ISO codes at all, so every address it resolves would
   * insert a NULL location_region. Under MySQL's strict mode that is an
   * integrity constraint violation, which fails the tracking request and
   * loses the visit entirely -- the more precisely DB-IP can place a
   * visitor, the more certain the visit is to be dropped.
   *
   * A missing subdivision code falls back to the subdivision's English
   * name, which the Region report then shows as-is ("California" rather
   * than "CA"); anything else missing becomes an empty string, which the
   * reports already show as "Unknown".
   *
   * @param mixed $record
   *   A lookup result; anything other than a City model is returned as-is.
   *
   * @return mixed
   *   The normalized record.
   */
  public static function normalize(mixed $record): mixed {
    if (!$record instanceof City) {
      return $record;
    }

    $raw = $record->jsonSerialize();

    $raw['continent']['code'] ??= '';
    $raw['country']['iso_code'] ??= $raw['registered_country']['iso_code'] ?? '';

    $subdivision = $raw['subdivisions'][0] ?? [];
    $subdivision['iso_code'] ??= static::englishName($subdivision);
    $raw['subdivisions'][0] = $subdivision;

    $raw['city']['names']['en'] ??= static::englishName($raw['city'] ?? []);

    return new City($raw, ['en']);
  }

  /**
   * A record's English name, else its first name in any language, else ''.
   *
   * Truncated to the 128 characters visitors_geoip's region and city
   * columns hold.
   */
  private static function englishName(array $record): string {
    $names = $record['names'] ?? [];
    $name = (string) ($names['en'] ?? reset($names) ?: '');
    return mb_substr($name, 0, 128);
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
      $real_path = $this->locator->realpath();
      // realpath() on a missing file returns NULL; only construct a Reader
      // once, and remember there was nothing to read rather than re-checking
      // the filesystem on every single visit this process handles.
      $this->dbIpReader = $real_path ? new Reader($real_path) : FALSE;
    }

    return $this->dbIpReader ?: NULL;
  }

}
