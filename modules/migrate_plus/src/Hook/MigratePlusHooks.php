<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Plugin\Migration;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate_plus\Entity\MigrationGroup;
use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Hook implementations for migrate_plus.
 */
class MigratePlusHooks {

  public function __construct(
    private EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * Implements hook_migration_plugins_alter().
   */
  #[Hook(
    hook: 'migration_plugins_alter',
    order: Order::First,
  )]
  public function migrationPluginsAlter(array &$migrations): void {
    foreach (array_keys($migrations) as $id) {
      // Add the default class where empty.
      if (empty($migrations[$id]['class'])) {
        $migrations[$id]['class'] = Migration::class;
      }

      // Convert null idMap to an empty array.
      if (empty($migrations[$id]['idMap'])) {
        $migrations[$id]['idMap'] = [];
      }

      // For derived configuration entity-based migrations, strip the deriver
      // prefix so we can reference migrations by the IDs they specify (i.e.,
      // the migration that specifies "id: temp" can be referenced as "temp"
      // rather than "migration_config_deriver:temp").
      $prefix = 'migration_config_deriver:';
      if (str_starts_with($id, $prefix)) {
        $new_id = substr($id, strlen($prefix));
        $migrations[$new_id] = $migrations[$id];
        unset($migrations[$id]);
        $id = $new_id;
      }

      // Integrate shared group configuration into the migration.
      if (empty($migrations[$id]['migration_group'])) {
        $migrations[$id]['migration_group'] = 'default';
      }
      $group = MigrationGroup::load($migrations[$id]['migration_group']);
      if (empty($group)) {
        // If the specified group does not exist, create it. Provide a little
        // more for the 'default' group.
        $group_properties = [];
        $group_properties['id'] = $migrations[$id]['migration_group'];
        if ($migrations[$id]['migration_group'] == 'default') {
          $group_properties['label'] = 'Default';
          $group_properties['description'] = 'A container for any migrations not explicitly assigned to a group.';
        }
        else {
          $group_properties['label'] = $group_properties['id'];
          $group_properties['description'] = '';
        }
        $group = MigrationGroup::create($group_properties);
        $group->save();
      }
      $shared_configuration = $group->get('shared_configuration');
      if (empty($shared_configuration)) {
        continue;
      }
      foreach ($shared_configuration as $key => $group_value) {
        $migration_value = $migrations[$id][$key] ?? NULL;
        // Where both the migration and the group provide arrays, replace
        // recursively (so each key collision is resolved in favor of the
        // migration).
        if (is_array($migration_value) && is_array($group_value)) {
          $merged_values = array_replace_recursive($group_value, $migration_value);
          $migrations[$id][$key] = $merged_values;
        }
        // Where the group provides a value the migration doesn't, use the group
        // value.
        elseif (is_null($migration_value)) {
          $migrations[$id][$key] = $group_value;
        }
        // Otherwise, the existing migration value overrides the group value.
      }
    }
  }

  /**
   * Implements hook_migrate_prepare_row().
   */
  #[Hook(hook: 'migrate_prepare_row')]
  public function prepareRow(Row $row, MigrateSourceInterface $source, MigrationInterface $migration): void {
    $this->eventDispatcher->dispatch(new MigratePrepareRowEvent($row, $source, $migration), MigrateEvents::PREPARE_ROW);
  }

}
