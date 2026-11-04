<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\BulkEdit;

use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;

/**
 * An import strategy for one row of the per-item bulk edit grid.
 *
 * Never saved. It reuses the importer's per-field processing, so a grid
 * cell takes exactly the text a CSV import column would.
 */
final class GridStrategy extends MukurtuImportStrategy {

  /**
   * Creates a strategy for one bundle.
   */
  public static function forBundle(string $entity_type_id, string $bundle): self {
    $strategy = new self(['id' => 'mukurtu_bulk_edit_grid'], 'mukurtu_import_strategy');
    $strategy->setTargetEntityTypeId($entity_type_id);
    $strategy->setTargetBundle($bundle);
    return $strategy;
  }

  /**
   * Returns a one-row migration definition that updates an existing item.
   *
   * @param array<string, string> $row
   *   Source values keyed by target: the entity ID, the langcode for a
   *   translation row, and the changed fields' cell text.
   * @param string[] $fields
   *   The changed fields.
   * @param string $log
   *   The revision log message.
   */
  public function rowDefinition(array $row, array $fields, string $log): array {
    $entity_type_id = $this->getTargetEntityTypeId();
    $mapping = array_map(fn($key) => ['source' => $key, 'target' => $key], array_keys($row));
    $this->setMapping($mapping);

    // Translation rows send their langcode; the importer then leaves fields
    // shared by all translations alone. Original-language rows don't, so
    // they update the item itself, shared fields included.
    $ids = [];
    foreach (array_keys($row) as $key) {
      if (!in_array($key, $fields, TRUE)) {
        $ids[$key] = ['type' => 'string'];
      }
    }

    $destination = [
      'plugin' => "mukurtu_grid_entity:$entity_type_id",
      'default_bundle' => $this->getTargetBundle(),
      'overwrite_properties' => $this->getOverwriteProperties($mapping),
      'validate' => TRUE,
      'grid_fields' => $fields,
    ];
    if ($this->isTranslationImport(array_keys($row))) {
      $destination['translations'] = TRUE;
    }

    return [
      // Unique, so a failed row can't leave the next one "busy".
      'id' => 'mukurtu_bulk_edit_grid_' . bin2hex(random_bytes(6)),
      'label' => 'Bulk edit grid row',
      'source' => [
        'plugin' => 'embedded_data',
        'data_rows' => [$row],
        'ids' => $ids,
      ],
      'process' => $this->getProcess($mapping),
      'destination' => $destination,
      'idMap' => ['plugin' => 'null'],
      'mukurtu_import_message' => $log,
    ];
  }

}
