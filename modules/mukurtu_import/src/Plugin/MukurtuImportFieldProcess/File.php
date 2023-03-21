<?php

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the mukurtu_import_field_process.
 *
 * @MukurtuImportFieldProcess(
 *   id = "file",
 *   label = @Translation("File"),
 *   field_types = {
 *     "file",
 *   },
 *   weight = 0,
 *   description = @Translation("File.")
 * )
 */
class File extends MukurtuImportFieldProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    $cardinality = $field_config->getFieldStorageDefinition()->getCardinality();
    $multiple = $cardinality == -1 || $cardinality > 1;
    $process = [];
    if ($multiple) {
      $process[] = [
        'plugin' => 'explode',
        'delimiter' => ';',
      ];
    }
    $process[] = [
      'plugin' => 'mukurtu_fileitem',
      'upload_location' => $context['upload_location'] ?? '',
    ];
    $process[0]['source'] = $source;
    return $process;
  }

}
