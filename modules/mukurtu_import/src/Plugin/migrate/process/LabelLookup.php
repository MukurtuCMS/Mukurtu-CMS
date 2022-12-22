<?php

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
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

class LabelLookup extends ProcessPluginBase
{
  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property)
  {
    $allowedValues = [];
    $entityType = $this->configuration['entity_type'];
    $fieldName = $this->configuration['field_name'];
    $bundle = $this->configuration['bundle'];

    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($entityType, $bundle);
    if (array_key_exists($fieldName, $fields)) {
      /** @var \Drupal\field\Entity\FieldConfig $fieldConfig */
      $fieldConfig = $fields[$fieldName];
      $allowedValues = $fieldConfig->getSetting('allowed_values');
      $lowercaseValues = array_map('strtolower', $allowedValues);
      $machineName = array_search(strtolower($value), $lowercaseValues);

      if ($machineName) {
        return $machineName;
      }
      // If lookup does not resolve to a valid machine name, return the value.
      else {
        return $value;
      }
    }
    else {
      // Error out.
    }
  }
}
