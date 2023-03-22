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
    $cardinality = $field_config->getFieldStorageDefinition()->getCardinality();
    $multivalue_delimiter = $context['multivalue_delimiter'] ?? ';';
    $multiple = $cardinality == -1 || $cardinality > 1;

    if ($multiple) {
      return [
        'plugin' => 'explode',
        'source' => $source,
        'delimiter' => $multivalue_delimiter,
      ];
    }

    return $source;
  }

}
