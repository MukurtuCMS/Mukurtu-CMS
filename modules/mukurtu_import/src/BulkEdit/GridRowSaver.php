<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\BulkEdit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_import\Plugin\migrate\destination\GridEntityContent;

/**
 * Saves one row of the per-item bulk edit grid through the importer.
 */
class GridRowSaver {

  /**
   * Applies cell text to an item and saves it, unless a change is invalid.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $item
   *   The item, in the row's language.
   * @param array<string, string> $cells
   *   The changed cells' text, keyed by field name, in CSV import format.
   * @param string $log
   *   The revision log message.
   *
   * @return array<string, string[]>
   *   Problems keyed by field name, with an empty-string key for those not
   *   tied to one field. Empty when the item was saved.
   */
  public function save(ContentEntityInterface $item, array $cells, string $log): array {
    if (!$cells) {
      return [];
    }
    $entity_type = $item->getEntityType();
    $row = [$entity_type->getKey('id') => (string) $item->id()];
    if (!$item->isDefaultTranslation()) {
      $row[$entity_type->getKey('langcode')] = $item->language()->getId();
    }
    $row += $cells;

    $strategy = GridStrategy::forBundle($item->getEntityTypeId(), $item->bundle());
    $definition = $strategy->rowDefinition($row, array_keys($cells), $log);
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($definition);
    $executable = new GridRowExecutable($migration, new MigrateMessage());
    try {
      $executable->import();
    }
    finally {
      $migration->setStatus(MigrationInterface::STATUS_IDLE);
    }

    $destination = $migration->getDestinationPlugin();
    $errors = $destination instanceof GridEntityContent ? $destination->getFieldErrors() : [];
    // Conversion problems, such as an ambiguous term name, arrive as
    // "migration:field:plugin: message".
    // The destination's own errors also arrive here, joined into one message.
    $known = array_merge([], ...array_values($errors));
    foreach ($executable->messages as $message) {
      if (preg_match('/^[^:\s]+:([^:\s]+):[^:\s]+: (.*)$/s', $message, $parts) && array_key_exists($parts[1], $cells)) {
        $errors[$parts[1]][] = $parts[2];
      }
      elseif (!in_array($message, $known, TRUE) && $message !== implode("\n", $known)) {
        $errors[''][] = self::withoutServerPath($message);
      }
    }
    return $errors;
  }

  /**
   * Removes the file and line core adds to an unexpected error's message.
   *
   * Editors don't need the server path.
   */
  public static function withoutServerPath(string $message): string {
    return preg_replace('/ \([^()\s]+\.(?:php|module|inc|install):\d+\)$/', '', $message);
  }

}
