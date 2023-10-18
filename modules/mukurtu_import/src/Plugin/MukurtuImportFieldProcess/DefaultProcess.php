<?php

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the mukurtu_import_field_process.
 *
 * @MukurtuImportFieldProcess(
 *   id = "default",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "*",
 *   },
 *   weight = 0,
 *   description = @Translation("Default Process Plugin.")
 * )
 */
class DefaultProcess extends MukurtuImportFieldProcessPluginBase {
  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    $multivalue_delimiter = $context['multivalue_delimiter'] ?? ';';

    if ($this->isMultiple($field_config)) {
      return [
        'plugin' => 'explode',
        'source' => $source,
        'delimiter' => $multivalue_delimiter,
      ];
    }

    return $source;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatDescription(FieldDefinitionInterface $field_config, $field_property = NULL) {
    $type = $this->getSchemaDescription($field_config);
    if ($type) {
      if ($this->isMultiple($field_config)) {
        return t("@type values, separated by your selected multi-value delimiter.", ['@type' => $type]);
      }
      return t('@type value.', ['@type' => $type]);
    }
    if ($this->isMultiple($field_config)) {
      return t("No special format, the values are used directly, separated by your selected multi-value delimiter.");
    }
    return t('No special format, the value is used directly.');
  }
}
