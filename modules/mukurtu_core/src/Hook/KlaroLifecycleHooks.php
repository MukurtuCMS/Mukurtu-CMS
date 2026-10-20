<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Installer\InstallerKernel;

/**
 * Keeps a site's Klaro configuration when Klaro is uninstalled and re-enabled.
 *
 * The service is fetched lazily rather than injected, so kernel tests that
 * enable mukurtu_core without its full dependency tree still build.
 *
 * @see \Drupal\mukurtu_core\Service\KlaroConfigKeeper
 */
class KlaroLifecycleHooks {

  /**
   * Implements hook_module_preuninstall().
   */
  #[Hook('module_preuninstall')]
  public function modulePreuninstall(string $module, bool $is_syncing = FALSE): void {
    // During a config import the incoming configuration is authoritative.
    if ($module === 'klaro' && !$is_syncing) {
      \Drupal::service('mukurtu_core.klaro_config_keeper')->snapshot();
    }
  }

  /**
   * Implements hook_modules_installed().
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules, bool $is_syncing = FALSE): void {
    // The profile's own config/install covers Klaro during site installation.
    if (in_array('klaro', $modules, TRUE) && !$is_syncing && !InstallerKernel::installationAttempted()) {
      \Drupal::service('mukurtu_core.klaro_config_keeper')->restore();
    }
  }

}
