<?php

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;
use Drupal\mukurtu_import\Attribute\MukurtuImportFieldProcess;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the mukurtu_import_field_process.
 */
#[MukurtuImportFieldProcess(
  id: 'list_string',
  label: new TranslatableMarkup('List String'),
  description: new TranslatableMarkup('List String.'),
  field_types: ['list_string'],
  weight: 0,
)]
class ListString extends MukurtuImportFieldProcessPluginBase {
  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    $multivalue_delimiter = $context['multivalue_delimiter'] ?? self::MULTIVALUE_DELIMITER;
    $process = [];
    if ($this->isMultiple($field_config)) {
      $process[] = [
        'plugin' => 'explode',
        'delimiter' => $multivalue_delimiter,
      ];
    }
    $process[] = [
      'plugin' => 'label_lookup',
      'entity_type' => $field_config->getTargetEntityTypeId(),
      'field_name' => $field_config->getName(),
      'bundle' => $field_config->getTargetBundle(),
    ];
    $process[0]['source'] = $source;
    return $process;
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
