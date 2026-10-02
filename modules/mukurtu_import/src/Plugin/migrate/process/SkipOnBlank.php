<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Stops processing the current property when the cell is blank.
 *
 * Unlike core's skip_on_empty, "0" is a real value and passes through, so a
 * boolean column can still be set to 0. A skipped property is left unset:
 * new content gets the field's default, and ProtocolAwareEntityContent keeps
 * the stored value on updates.
 *
 * @see \Drupal\mukurtu_import\Plugin\migrate\destination\ProtocolAwareEntityContent::restoreBlankedFieldValues()
 */
#[MigrateProcess('mukurtu_skip_on_blank')]
class SkipOnBlank extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($value === NULL || (is_string($value) && trim($value) === '')) {
      $this->stopPipeline();
      return NULL;
    }
    return $value;
  }

}
