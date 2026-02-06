<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;

/**
 * Hook implementations for mukurtu_import.
 */
class MukurtuImportHooks {

  /**
   * Implements hook_migrate_prepare_row().
   */
  #[Hook('migrate_prepare_row')]
  public function migratePrepareRow(Row $row, MigrateSourceInterface $source, MigrationInterface $migration) {
    // Only add row hash for CSV source plugin.
    if ($source->getPluginId() !== 'csv') {
      return;
    }

    // Get all row source data.
    $rowData = $row->getSource();

    // Create a hash of the row data.
    $hash = hash('sha256', serialize($rowData));

    // Add the hash to the row as a source property.
    $row->setSourceProperty('_row_hash', $hash);
  }

}
