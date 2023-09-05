<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Return the array column for the given key.
 *
 * @MigrateProcessPlugin(
 *   id = "array_column",
 *   handle_multiples = TRUE
 * )
 */
class ArrayColumn extends ProcessPluginBase {
  /**
   * {@inheritDoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $column_key = $this->configuration['column_key'];
    if (!$column_key || empty($value)) {
      return $value;
    }
    return array_column($value, $column_key);
  }

}
