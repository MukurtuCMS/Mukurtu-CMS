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
  id: 'local_contexts_label_and_notice',
  label: new TranslatableMarkup('Local Contexts Label and Notice'),
  description: new TranslatableMarkup('Local Contexts Label and Notice.'),
  field_types: ['local_contexts_label_and_notice'],
  weight: 0,
)]
class LocalContextsLabelAndNotice extends MukurtuImportFieldProcessPluginBase {

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
      'plugin' => 'callback',
      'callable' => 'trim',
    ];
    $process[] = [
      'plugin' => 'local_contexts_label_lookup',
    ];
    $process[0]['source'] = $source;
    return $process;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatDescription(FieldDefinitionInterface $field_config, $field_property = NULL) {
    if ($this->isMultiple($field_config)) {
      return t('Label or notice names, separated by your selected multi-value delimiter.');
    }
    return t('The label or notice name, as it appears in Local Contexts.');
  }

}
