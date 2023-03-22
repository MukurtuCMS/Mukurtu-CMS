<?php

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the mukurtu_import_field_process.
 *
 * @MukurtuImportFieldProcess(
 *   id = "image",
 *   label = @Translation("Image"),
 *   field_types = {
 *     "image",
 *   },
 *   weight = 0,
 *   description = @Translation("Image.")
 * )
 */
class Image extends MukurtuImportFieldProcessPluginBase {
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
      'plugin' => 'mukurtu_imageitem',
      'upload_location' => $context['upload_location'] ?? '',
    ];
    $process[0]['source'] = $source;
    return $process;
  }

}
