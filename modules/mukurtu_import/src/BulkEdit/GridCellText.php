<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\BulkEdit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\mukurtu_export\Entity\CsvExporter;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;

/**
 * Writes a field's current value as grid cell text, as the CSV export does.
 *
 * It uses the settings the "Default local settings (metadata only)" export
 * preset ships with: terms and people by name, everything else by ID, the
 * text the importer reads back. They're fixed here rather than read from the
 * preset, which a site can change or delete.
 */
class GridCellText {

  /**
   * Separates a cell's values, as the importer expects.
   */
  public const DELIMITER = ';';

  /**
   * The export settings, built once.
   */
  protected ?CsvExporter $exporter = NULL;

  /**
   * Returns a field's value as cell text.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $item
   *   The item, in the row's language.
   * @param string $field
   *   The field name.
   */
  public function text(ContentEntityInterface $item, string $field): string {
    $context = ['results' => ['config' => $this->exporter()]];
    $event = new EntityFieldExportEvent('csv', $item, $field, $context);
    \Drupal::service('mukurtu_export.csv_field_export_event_subscriber')->exportField($event);
    return implode(self::DELIMITER, array_map('strval', (array) $event->getValue()));
  }

  /**
   * Returns the unsaved export settings the grid uses.
   */
  protected function exporter(): CsvExporter {
    return $this->exporter ??= CsvExporter::create([
      'id' => 'mukurtu_bulk_edit_grid',
      'multivalue_delimiter' => self::DELIMITER,
      'local_contexts_delimiter' => '>',
      'default_format' => 'basic_html',
      'field_id' => 'id',
      'field_file' => 'id',
      'field_image' => 'id',
      'entity_reference_node' => 'id',
      'entity_reference_media' => 'id',
      'entity_reference_taxonomy_term' => 'name',
      'entity_reference_user' => 'username',
      'entity_reference_paragraph' => 'entity',
      'entity_reference_multipage_item' => 'entity',
    ]);
  }

}
