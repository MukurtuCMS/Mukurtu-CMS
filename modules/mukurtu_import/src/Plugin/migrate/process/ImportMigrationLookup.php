<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Looks up a destination ID from a previous import migration's ID map.
 *
 * Unlike core's migration_lookup, this plugin does not require migrations
 * to be registered with the plugin manager. It queries the ID map table
 * directly, which works for transient migrations created via
 * createStubMigration().
 *
 * If the lookup fails, the original value passes through unchanged,
 * allowing downstream process plugins (e.g., mukurtu_entity_lookup) to
 * attempt their own resolution.
 *
 * Configuration:
 * - migration_ids: An array of migration IDs to look up.
 */
#[MigrateProcess('import_migration_lookup')]
class ImportMigrationLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // If value is already numeric (an entity ID), pass it through.
    if (is_numeric($value)) {
      return $value;
    }

    $migration_ids = (array) ($this->configuration['migration_ids'] ?? []);

    foreach ($migration_ids as $migration_id) {
      $table = 'migrate_map_' . $migration_id;
      if (!$this->database->schema()->tableExists($table)) {
        continue;
      }

      $dest_id = $this->database->select($table, 'm')
        ->fields('m', ['destid1'])
        ->condition('sourceid1', $value)
        ->execute()
        ->fetchField();

      if ($dest_id !== FALSE) {
        return $dest_id;
      }
    }

    // Not found — pass the original value through for downstream plugins.
    return $value;
  }

}
