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

}
