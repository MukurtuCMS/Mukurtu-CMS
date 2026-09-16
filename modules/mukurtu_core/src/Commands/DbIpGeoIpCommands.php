<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Commands;

use Drupal\mukurtu_core\Service\DbIpDownloadService;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the DB-IP fallback geolocation database.
 *
 * @see \Drupal\mukurtu_core\Service\DbIpDownloadService
 * @see \Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService
 */
class DbIpGeoIpCommands extends DrushCommands {

  /**
   * Constructs the command.
   */
  public function __construct(
    private readonly DbIpDownloadService $downloadService,
  ) {
    parent::__construct();
  }

  /**
   * Downloads DB-IP's free City Lite geolocation database.
   *
   * Installed to the same directory MaxMind's own database would use
   * (Visitors > GeoIP settings), under a different filename, so a site with
   * both keeps using MaxMind's better precision and only falls back to this
   * one when MaxMind has nothing. Unlike MaxMind's GeoLite2, DB-IP's City
   * Lite data (CC BY 4.0) needs no account or license key.
   *
   * @command mukurtu:geoip:download-dbip
   * @aliases mukurtu-geoip-download-dbip
   *
   * @usage drush mukurtu:geoip:download-dbip
   *   Downloads (or refreshes) the DB-IP fallback database.
   */
  public function download(): void {
    if ($this->downloadService->download()) {
      $this->output()->writeln('DB-IP fallback geolocation database installed.');
      return;
    }

    $this->output()->writeln('Could not download the DB-IP fallback geolocation database. See the site log for details.');
  }

}
