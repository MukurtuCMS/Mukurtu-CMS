<?php

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the mukurtu_import_field_process.
 *
 * @MukurtuImportFieldProcess(
 *   id = "entity_reference",
 *   label = @Translation("Entity Reference"),
 *   field_types = {
 *     "entity_reference",
 *   },
 *   weight = 0,
 *   description = @Translation("Entity Reference.")
 * )
 */
class EntityReference extends MukurtuImportFieldProcessPluginBase {
  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    $cardinality = $field_config->getFieldStorageDefinition()->getCardinality();
    $multivalue_delimiter = $context['multivalue_delimiter'] ?? ';';
    $multiple = $cardinality == -1 || $cardinality > 1;
    $process = [];

    $refType = $field_config->getSetting('target_type');

    if ($refType == 'taxonomy_term') {
      $targetBundles = $field_config->getSetting('handler_settings')['target_bundles'] ?? [];
      $allTargetBundles = array_keys($targetBundles);
      $targetBundle = reset($allTargetBundles);
      $process[] = [
        'plugin' => $field_config->getSetting('handler_settings')['auto_create'] ? 'mukurtu_entity_generate' : 'mukurtu_entity_lookup',
        'value_key' => 'name',
        'bundle_key' => 'vid',
        'bundle' => $targetBundle,
        'entity_type' => $field_config->getSetting('target_type'),
        'ignore_case' => TRUE,
      ];
      return $process;
    }

    if (in_array($refType, ['community', 'media', 'node', 'protocol'])) {
      $process = [];
      $process[] = [
        'plugin' => 'explode',
        'source' => $source,
        'delimiter' => $multivalue_delimiter,
      ];
      $process[] = [
        'plugin' => 'mukurtu_entity_lookup',
        'value_key' => \Drupal::entityTypeManager()->getDefinition($refType)->getKey('label'),
        'ignore_case' => TRUE,
        'entity_type' => $field_config->getSetting('target_type'),
      ];
      return $process;
    }

    // User ref. Only difference is value_key is set to 'name'.
    if ($refType == 'user') {
      $process = [];
      $process[] = [
        'plugin' => 'explode',
        'source' => $source,
        'delimiter' => $multivalue_delimiter,
      ];
      $process[] = [
        'plugin' => 'mukurtu_entity_lookup',
        'value_key' => 'name',
        'ignore_case' => TRUE,
        'entity_type' => $field_config->getSetting('target_type'),
      ];
      return $process;
    }

    // @todo Paragraphs.
    // @todo Users.

    return $process;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_config): bool {
    $refType = $field_config->getSetting('target_type') ?? [];
    return in_array($refType, ['community','media','node','protocol','taxonomy_term','user']);
  }

}
