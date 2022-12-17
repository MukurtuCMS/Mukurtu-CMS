<?php

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * For list (text) fields, perform lookup for machine name based on label.
 *
 * @MigrateProcessPlugin(
 *   id = "label_lookup"
 * )
 *
 * @code
 * field_list_text:
 *   plugin: label_lookup
 *   source: list_text
 * @endcode
 *
 */

class LabelLookup extends ProcessPluginBase
{
  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property)
  {
    dpm($value);
    dpm($row);
  }
}
