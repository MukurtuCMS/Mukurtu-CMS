<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\mukurtu_core\Service\DbIpDownloadService;

/**
 * Keeps the DB-IP fallback geolocation database installed and reasonably fresh.
 *
 * @see \Drupal\mukurtu_core\Service\DbIpDownloadService
 * @see \Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService
 */
class GeoIpFallbackHooks {

  /**
   * Constructs the hook implementations.
   */
  public function __construct(
    private readonly DbIpDownloadService $downloadService,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_modules_installed().
   *
   * The zero-touch happy path: try the download immediately once
   * visitors_geoip is enabled (a fresh site install, or an existing site
   * updating to a mukurtu_core version carrying this feature), rather than
   * waiting for cron to notice. download() never throws, so this cannot
   * fail the install/update batch that triggered it; cron's own staleness
   * check is what retries if this attempt has no network to work with.
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules): void {
    if (in_array('visitors_geoip', $modules, TRUE)) {
      $this->downloadService->download();
    }
  }

  /**
   * Implements hook_cron().
   *
   * A monthly-ish safety net: installs the database on any site that
   * enabled visitors_geoip before this feature existed, and refreshes it
   * once DB-IP publishes a new one. Deliberately not queued: DB-IP releases
   * monthly, so the at-most-once-a-month blocking download this can trigger
   * (only when cron runs inline on a page request rather than a real cron
   * job -- see core's own update_cron(), which makes the same trade-off
   * fetching Drupal's update XML feed) is accepted rather than adding a
   * queue worker for something this infrequent.
   */
  #[Hook('cron')]
  public function cron(): void {
    if ($this->moduleHandler->moduleExists('visitors_geoip') && $this->downloadService->isStale()) {
      $this->downloadService->download();
    }
  }

}
