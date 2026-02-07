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
  id: 'entity_reference',
  label: new TranslatableMarkup('Entity Reference'),
  description: new TranslatableMarkup('Entity Reference.'),
  field_types: ['entity_reference'],
  weight: 0,
)]
class EntityReference extends MukurtuImportFieldProcessPluginBase {
  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []) {
    $cardinality = $field_config->getFieldStorageDefinition()->getCardinality();
    $multivalue_delimiter = $context['multivalue_delimiter'] ?? self::MULTIVALUE_DELIMITER;
    $refType = $field_config->getSetting('target_type');
    $multiple = $cardinality == -1 || $cardinality > 1;
    $process = [];

    if ($multiple) {
      $process[] = [
        'plugin' => 'explode',
        'delimiter' => $multivalue_delimiter,
      ];
    }

    // Resolve UUIDs.
    $process[] = [
      'plugin' => 'uuid_lookup',
      'entity_type' => $field_config->getSetting('target_type'),
    ];

    // Default.
    $refProcess = [
      'plugin' => 'mukurtu_entity_lookup',
      'value_key' => 'uuid',
      'ignore_case' => TRUE,
      'entity_type' => $field_config->getSetting('target_type'),
    ];

    if ($refType == 'taxonomy_term') {
      $targetBundles = $field_config->getSetting('handler_settings')['target_bundles'] ?? [];
      $allTargetBundles = array_keys($targetBundles);
      $targetBundle = reset($allTargetBundles);

      $refProcess = [
        'plugin' => $field_config->getSetting('handler_settings')['auto_create'] ? 'mukurtu_entity_generate' : 'mukurtu_entity_lookup',
        'value_key' => 'name',
        'bundle_key' => 'vid',
        'bundle' => $targetBundle,
        'entity_type' => $field_config->getSetting('target_type'),
        'ignore_case' => TRUE,
      ];
    }

    if (in_array($refType, ['community', 'media', 'node', 'protocol'])) {
      $refProcess = [
        'plugin' => 'mukurtu_entity_lookup',
        'value_key' => \Drupal::entityTypeManager()->getDefinition($refType)->getKey('label'),
        'ignore_case' => TRUE,
        'entity_type' => $field_config->getSetting('target_type'),
      ];
    }

    // User ref. Only difference is value_key is set to 'name'.
    if ($refType == 'user') {
      $refProcess = [
        'plugin' => 'mukurtu_entity_lookup',
        'value_key' => 'name',
        'ignore_case' => TRUE,
        'entity_type' => $field_config->getSetting('target_type'),
      ];
    }

    $process[] = $refProcess;

    // Attach source value to the first process.
    $process[0]['source'] = $source;

    return $process;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_config): bool {
    $refType = $field_config->getSetting('target_type') ?? [];
    return in_array($refType, ['community','media','node','protocol','taxonomy_term','user']);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatDescription(FieldDefinitionInterface $field_config, $field_property = NULL) {
    $multiple = $this->isMultiple($field_config);
    $refType = $field_config->getSetting('target_type');

    if ($refType == 'user') {
      $description = $multiple ? "Usernames or User IDs, separated by your selected multi-value delimiter." : "The username or user ID.";
      return t($description);
    }

    if ($refType == 'taxonomy_term') {
      $auto_create = $field_config->getSetting('handler_settings')['auto_create'] ?? FALSE;
      $description = $multiple ? "Taxonomy term names, IDs, or UUIDs, separated by your selected multi-value delimiter. Each name must be exact and match only one term in that vocabulary." : "Taxonomy term name, ID, or UUID. The name must be exact and match only one term in that vocabulary.";
      if ($auto_create) {
        $description .= " New terms will be created if they do not already exist.";
      }
      return t($description);
    }

    $description = $multiple ? "IDs, UUIDs, or titles of the references, separated by your selected multi-value delimiter. Each title must be exact and match only one item." : "ID, UUID, or title of the reference. The title must be exact and match only one item.";
    return t($description);
  }

}
