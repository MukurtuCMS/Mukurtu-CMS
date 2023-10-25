<?php

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * For list text fields (fields of type 'list_string'), do a lookup for machine
 * name based on label.
 *
 * Accepts three mandatory config values: entity type, field name, and bundle.
 *
 * @MigrateProcessPlugin(
 *   id = "label_lookup"
 * )
 *
 * @code
 *  plugin: label_lookup
 *  source: list_text
 *  entity_type: entity_type
 *  field_name: field_name
 *  bundle: bundle
 *
 * @endcode
 *
 */

class LabelLookup extends ProcessPluginBase {
  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $allowedValues = [];
    $entityType = $this->configuration['entity_type'];
    $fieldName = $this->configuration['field_name'];
    $bundle = $this->configuration['bundle'];

    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($entityType, $bundle);

    /** @var \Drupal\field\Entity\FieldConfig $fieldConfig */
    if ($fieldConfig = $fields[$fieldName] ?? NULL) {
      $allowedValues = $fieldConfig->getSetting('allowed_values');
      if (isset($allowedValues[$value])) {
        return $value;
      }

      $lowercaseValues = array_map('mb_strtolower', $allowedValues);
      if ($key = array_search(mb_strtolower($value), $lowercaseValues)) {
        return $key;
      }
    }

    return $value;
  }

}
