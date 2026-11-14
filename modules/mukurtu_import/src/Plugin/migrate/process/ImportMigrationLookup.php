<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateException;
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
 *
 * The plugin searches across ALL sourceid columns (sourceid1, sourceid2, etc.)
 * in each migration's ID map table, enabling lookup by any registered source
 * key. For example, a media migration may use both the media name and filename
 * as source IDs, allowing downstream CSVs to reference media by either value.
 *
 * A value that matches more than one destination entity (for example, a
 * title shared by several rows in an upstream CSV) is ambiguous. Like
 * mukurtu_entity_lookup, the plugin then fails the row rather than silently
 * linking the first match.
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
    $schema = $this->database->schema();
    $matches = [];

    foreach ($migration_ids as $migration_id) {
      $table = 'migrate_map_' . $migration_id;
      if (!$schema->tableExists($table)) {
        continue;
      }

      // Discover all sourceid columns in this table.
      $sourceid_columns = [];
      for ($i = 1; $i <= 10; $i++) {
        $col = 'sourceid' . $i;
        if ($schema->fieldExists($table, $col)) {
          $sourceid_columns[] = $col;
        }
        else {
          break;
        }
      }

      // Search each sourceid column independently. Collect every distinct
      // destination so a value shared by several upstream rows is detected
      // instead of resolving to whichever row the database returns first.
      foreach ($sourceid_columns as $col) {
        $dest_ids = $this->database->select($table, 'm')
          ->fields('m', ['destid1'])
          ->condition($col, $value)
          ->isNotNull('destid1')
          ->distinct()
          ->execute()
          ->fetchCol();
        foreach ($dest_ids as $dest_id) {
          $matches[$dest_id] = $dest_id;
        }
      }
    }

    if (count($matches) > 1) {
      throw new MigrateException(sprintf('"%s" is ambiguous, multiple items share the same label. Try referencing the item\'s ID.', $value));
    }

    if ($matches) {
      return reset($matches);
    }

    // Not found — pass the original value through for downstream plugins.
    return $value;
  }

}
