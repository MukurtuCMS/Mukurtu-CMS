<?php

namespace Drupal\csv_source_yield_test\Plugin\migrate\source;

use Drupal\migrate_source_csv\Plugin\migrate\source\CSV;
use League\Csv\Reader;

/**
 * Yields each image and sku.
 *
 * @MigrateSource(
 *   id = "yield_rows"
 * )
 */
class YieldRows extends CSV {

  /**
   * {@inheritdoc}
   */
  public function initializeIterator() {
    return $this->getYield(parent::initializeIterator());
  }

  /**
   * Prepare a test row using yield.
   *
   * @param \League\Csv\Reader $reader
   *   The CSV reader.
   *
   * @codingStandardsIgnoreStart
   *
   * @return \Generator
   *   A generator with only the id value.
   *
   * @codingStandardsIgnoreEnd
   */
  public function getYield(Reader $reader) {
    foreach ($reader as $row) {
      $new_row = [];
      $new_row['id'] = $row['id'];
      yield($new_row);
    }
  }

}
