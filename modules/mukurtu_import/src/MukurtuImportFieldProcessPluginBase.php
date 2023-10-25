<?php

namespace Drupal\mukurtu_import;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Base class for mukurtu_import_field_process plugins.
 */
class MukurtuImportFieldProcessPluginBase extends PluginBase implements MukurtuImportFieldProcessInterface {
  const MULTIVALUE_DELIMITER = ";";
  /**
   * An array of field types the process supports.
   *
   * @var array
   */
  public $field_types = [];
  public $weight = 0;



  /**
   * {@inheritdoc}
   */
  public function label() {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_config): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatDescription(FieldDefinitionInterface $field_config, $field_property = NULL) {
    return '';
  }

  /**
   * Check if a field is configured to use multiple values.
   *
   * @return bool
   *  Returns TRUE if the field uses multiple values.
   */
  protected function isMultiple(FieldDefinitionInterface $field_definition) {
    $cardinality = $field_definition->getFieldStorageDefinition()->getCardinality();
    return $cardinality == -1 || $cardinality > 1;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSchemaDescription(FieldDefinitionInterface $field_definition) {
    $field_type_id = $field_definition->getType();
    $settings = $field_definition->getSettings();
    switch ($field_type_id) {
      case 'uuid':
        return t('UUID (e.g., 6b77cc9e-5fdf-4750-891e-e705b7bf323b)');
      case 'integer':
        return t('Integer');
      case 'string':
      case 'string_long':
        return t('Plain text');
      case 'text_with_summary':
      case 'text_long':
        return t('Formatted text');
      case 'boolean':
        return t('Boolean (0 or 1)');
      case 'float':
        return t('Decimal number');
      case 'created':
      case 'changed':
        return t('Unix timestamp');
      case 'datetime':
        if (isset($settings['datetime_type']) && $settings['datetime_type'] === 'date') {
          return t('ISO 8601 date only (e.g., YYYY-MM-DD)');
        }
        return t('ISO 8601 date and time (YYYY-MM-DDTH:i:s)');
      case 'language':
        return t('Language code');
      case 'original_date':
        return t('YYYY, YYYY-MM, or YYYY-MM-DD');
      case 'local_contexts_project':
        return t('Local Contexts Project ID');
      case 'local_contexts_label_and_notice':
        return t('Local Contexts Project ID:Label/Notice ID');
    }

    return NULL;
  }

}
