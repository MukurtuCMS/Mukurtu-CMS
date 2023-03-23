<?php

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;
use Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess\EntityReference;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the mukurtu_import_field_process.
 *
 * @MukurtuImportFieldProcess(
 *   id = "entity_reference_revisions",
 *   label = @Translation("Entity Reference Revisions"),
 *   field_types = {
 *     "entity_reference_revisions",
 *   },
 *   weight = 0,
 *   description = @Translation("Entity Reference Revisions.")
 * )
 */
class EntityReferenceRevisions extends EntityReference {
  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    $process = parent::getProcess($field_config, $source, $context);
    $last = end($process);
    $process[] = [
      'plugin' => 'current_entity_revision',
      'entity_type' => $last['entity_type'] ?? NULL,
    ];

    return $process;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_config): bool {
    $refType = $field_config->getSetting('target_type') ?? [];
    return in_array($refType, ['paragraph','community','media','node','protocol','taxonomy_term','user']);
  }

}
