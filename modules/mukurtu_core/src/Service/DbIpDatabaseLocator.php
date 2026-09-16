<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;

/**
 * Resolves where the DB-IP fallback database is stored.
 *
 * Deliberately not visitors_geoip's own geoip_path setting: that is an
 * admin-configured path meant for a manually-run MaxMind download, outside
 * the web root by default, with no guarantee it is writable by whatever
 * user actually runs PHP. On at least one real hosting model (Tugboat's
 * previews: the codebase is root-owned from the build phase, but
 * `drush updb -y` and cron both run as www-data) it is not -- confirmed
 * live, where writing here failed with a permissions error while the
 * download itself succeeded.
 *
 * Drupal's own public:// and private:// file systems do not have that
 * problem: every working Drupal site needs at least public:// writable by
 * whatever user runs PHP as a basic operating requirement (file uploads,
 * caching, and so on), and Tugboat's own build script already explicitly
 * chowns both to www-data. private:// is preferred when configured -- the
 * database is not sensitive, but there is no reason to put a 100MB+ binary
 * in the web-accessible public files directory when an alternative exists
 * -- and public:// otherwise, since a fresh site may not have configured
 * $settings['file_private_path'] yet.
 *
 * @see \Drupal\mukurtu_core\Service\DbIpDownloadService
 * @see \Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService
 */
class DbIpDatabaseLocator {

  /**
   * The filename the database is stored under, within its own subdirectory.
   */
  public const FILENAME = 'dbip-city-lite.mmdb';

  /**
   * Constructs the locator.
   */
  public function __construct(
    private readonly StreamWrapperManagerInterface $streamWrapperManager,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * The stream-wrapper URI the database is (or will be) stored at.
   */
  public function uri(): string {
    $scheme = $this->streamWrapperManager->isValidScheme('private') ? 'private' : 'public';
    return $scheme . '://mukurtu_core_geoip/' . self::FILENAME;
  }

  /**
   * A real filesystem path to the database, if it is actually present.
   *
   * GeoIp2\Database\Reader needs a real path, not a stream-wrapper URI.
   */
  public function realpath(): ?string {
    return $this->fileSystem->realpath($this->uri()) ?: NULL;
  }

}
