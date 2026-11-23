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
   *   The changed cells' text, keyed by column: a field name, or a field name
   *   and sub-property such as "field_media_image/alt". CSV import format.
   * @param string $log
   *   The revision log message.
   *
   * @return array<string, string[]>
   *   Problems keyed by column, with an empty-string key for those not tied
   *   to one. Empty when the item was saved.
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

    // Alternative text is part of its image. The importer replaces the whole
    // image field, so the current file goes along with the new text.
    $columns = $cells;
    foreach (array_keys($cells) as $column) {
      if (str_ends_with($column, '/alt')) {
        $file = substr($column, 0, -4) . '/target_id';
        $columns[$file] ??= (new GridCellText())->text($item, $file);
      }
    }
    $targets = [];
    foreach ($columns as $column => $text) {
      $source = str_replace('/', '__', $column);
      $targets[$source] = $column;
      $row[$source] = $text;
    }

    $strategy = GridStrategy::forBundle($item->getEntityTypeId(), $item->bundle());
    $definition = $strategy->rowDefinition($row, $targets, $log);
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
    $errors = [];
    foreach ($destination instanceof GridEntityContent ? $destination->getFieldErrors() : [] as $field => $messages) {
      $errors[self::column($field, $cells)] = array_merge($errors[self::column($field, $cells)] ?? [], $messages);
    }
    // Conversion problems, such as an ambiguous term name, arrive as
    // "migration:target:plugin: message".
    // The destination's own errors also arrive here, joined into one message.
    $known = array_merge([], ...array_values($errors));
    foreach ($executable->messages as $message) {
      if (preg_match('/^[^:\s]+:([^:\s]+):[^:\s]+: (.*)$/s', $message, $parts) && ($column = self::column($parts[1], $cells)) !== '') {
        $errors[$column][] = $parts[2];
      }
      elseif (!in_array($message, $known, TRUE) && $message !== implode("\n", $known)) {
        $errors[''][] = self::withoutServerPath($message);
      }
    }
    return $errors;
  }

  /**
   * Returns the changed column a field or target belongs to.
   *
   * An error on "field_media_image" belongs to its changed
   * "field_media_image/alt" column. Returns an empty string for none.
   */
  protected static function column(string $key, array $cells): string {
    if ($key === '' || isset($cells[$key])) {
      return $key;
    }
    $field = explode('/', $key, 2)[0];
    foreach (array_keys($cells) as $column) {
      if (explode('/', $column, 2)[0] === $field) {
        return $column;
      }
    }
    return '';
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
