<?php

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the mukurtu_import_field_process.
 *
 * @MukurtuImportFieldProcess(
 *   id = "link",
 *   label = @Translation("Link"),
 *   field_types = {
 *     "link",
 *   },
 *   weight = 0,
 *   description = @Translation("Link.")
 * )
 */
class Link extends MukurtuImportFieldProcessPluginBase {
  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    $multivalue_delimiter = $context['multivalue_delimiter'] ?? ';';
    $cardinality = $field_config->getFieldStorageDefinition()->getCardinality();
    $multiple = $cardinality == -1 || $cardinality > 1;
    $process = [];
    if ($multiple) {
      $process[] = [
        'plugin' => 'explode',
        'delimiter' => $multivalue_delimiter,
      ];
    }
    $process[] = [
      'plugin' => 'markdown_link',
    ];
    $process[0]['source'] = $source;
    return $process;
  }

}
