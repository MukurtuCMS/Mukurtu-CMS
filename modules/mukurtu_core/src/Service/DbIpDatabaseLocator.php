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
 * Drupal's own public:// and private:// file systems are both better bets,
 * but neither is unconditionally safe either: public:// is the one Drupal's
 * own installer requires be writable by whatever user runs PHP before it
 * will even complete an install, so a *successful* install always
 * guarantees it. private:// is only used if $settings['file_private_path']
 * is configured, and while Tugboat does configure one, nothing guarantees a
 * previously-created private files directory (persisting across builds on
 * the same preview) is still owned correctly if only Tugboat's *update*
 * phase -- not its build phase -- explicitly re-chowns it. So this is not a
 * single fixed choice: DbIpDownloadService tries every candidate in order
 * and uses whichever one it can actually write to, and a read checks every
 * candidate too, since a previously-installed database could be sitting at
 * any of them.
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
   * Candidate storage URIs, most preferred first.
   *
   * private:// first when configured -- the database is not sensitive, but
   * there is no reason to put a 100MB+ binary in the web-accessible public
   * files directory when a non-public alternative is available and working
   * -- then public:// always, as the fallback a successful Drupal install
   * already guarantees is writable.
   *
   * @return string[]
   */
  public function candidateUris(): array {
    $uris = [];
    if ($this->streamWrapperManager->isValidScheme('private')) {
      $uris[] = 'private://mukurtu_core_geoip/' . self::FILENAME;
    }
    $uris[] = 'public://mukurtu_core_geoip/' . self::FILENAME;
    return $uris;
  }

  /**
   * A real filesystem path to the database, if it is actually present.
   *
   * GeoIp2\Database\Reader needs a real path, not a stream-wrapper URI.
   * Checks every candidate location: whichever one download() last
   * succeeded in writing to is where this needs to look.
   */
  public function realpath(): ?string {
    foreach ($this->candidateUris() as $uri) {
      $path = $this->fileSystem->realpath($uri);
      if ($path) {
        return $path;
      }
    }

    return NULL;
  }

}
