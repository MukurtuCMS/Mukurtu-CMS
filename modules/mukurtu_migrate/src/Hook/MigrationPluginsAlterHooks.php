<?php

declare(strict_types=1);

namespace Drupal\mukurtu_migrate\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;

/**
 * Migration plugin definition alterations for mukurtu_migrate.
 */
class MigrationPluginsAlterHooks {

  /**
   * Implements hook_migration_plugins_alter().
   *
   * Whenever migration definitions are rebuilt, migrate_drupal connects to the
   * source database through core's system_site migration to read the legacy
   * Drupal version, and shows "Failed to connect to your database server" if
   * that fails. After a cache rebuild, every site without a reachable source
   * database shows that error, including sites whose Mukurtu 3 database was
   * removed after migrating. The Mukurtu 3 migration only uses migrations
   * tagged "Mukurtu 3", so removing system_site first skips the check.
   *
   * @see https://www.drupal.org/project/drupal/issues/3221087
   * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2551
   */
  #[Hook('migration_plugins_alter', order: Order::First)]
  public function migrationPluginsAlter(array &$definitions): void {
    unset($definitions['system_site']);
  }

}
