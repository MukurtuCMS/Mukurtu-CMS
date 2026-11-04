<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\BulkEdit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;

/**
 * Writes a field's current value as grid cell text, as the CSV export does.
 *
 * The local metadata-only export preset writes terms and people by name and
 * everything else by ID, the same text the importer reads back.
 */
class GridCellText {

  /**
   * The export preset whose format the grid uses.
   */
  public const EXPORTER = 'default_local_metadata_only';

  /**
   * Returns a field's value as cell text.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $item
   *   The item, in the row's language.
   * @param string $field
   *   The field name.
   */
  public function text(ContentEntityInterface $item, string $field): string {
    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $exporter */
    $exporter = \Drupal::entityTypeManager()->getStorage('csv_exporter')->load(self::EXPORTER);
    $context = ['results' => ['config_id' => self::EXPORTER]];
    $event = new EntityFieldExportEvent('csv', $item, $field, $context);
    \Drupal::service('mukurtu_export.csv_field_export_event_subscriber')->exportField($event);
    return implode($exporter->getMultivalueDelimiter(), array_map('strval', (array) $event->getValue()));
  }

}
