<?php

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the mukurtu_import_field_process.
 *
 * @MukurtuImportFieldProcess(
 *   id = "list_string",
 *   label = @Translation("List String"),
 *   field_types = {
 *     "list_string",
 *   },
 *   weight = 0,
 *   description = @Translation("List String.")
 * )
 */
class ListString extends MukurtuImportFieldProcessPluginBase {
  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    return [
      'plugin' => 'label_lookup',
      'source' => $source,
      'entity_type' => $field_config->getSetting('entity_type'),
      'field_name' => $field_config->getName(),
      'bundle' => $field_config->getSetting('bundle'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatDescription(FieldDefinitionInterface $field_config, $field_property = NULL) {
    $allowed_values = $field_config->getSetting('allowed_values');
    $value = key($allowed_values);

    $single_description = "The value (e.g., '%value') or the label of allowed values";
    $description = $this->isMultiple($field_config) ? "$single_description, separated by your selected multi-value delimiter." : $single_description;
    return t($description, ['%value' => $value]);
  }

}
