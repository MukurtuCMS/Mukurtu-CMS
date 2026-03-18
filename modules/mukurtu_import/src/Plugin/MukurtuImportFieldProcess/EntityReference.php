<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\MukurtuImportFieldProcess;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginBase;
use Drupal\mukurtu_import\Attribute\MukurtuImportFieldProcess;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class EntityReference extends MukurtuImportFieldProcessPluginBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * Creates a new instance of EntityReference.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []): array {
    $cardinality = $field_config->getFieldStorageDefinition()->getCardinality();
    $multivalue_delimiter = $context['multivalue_delimiter'] ?? self::MULTIVALUE_DELIMITER;
    $ref_type = $field_config->getSetting('target_type');
    $multiple = $cardinality == -1 || $cardinality > 1;
    $process = [];

    if ($multiple) {
      $process[] = [
        'plugin' => 'explode',
        'delimiter' => $multivalue_delimiter,
      ];
    }

    // Trim whitespace.
    $process[] = [
      'plugin' => 'callback',
      'callable' => 'trim',
    ];

    // Resolve UUIDs.
    $process[] = [
      'plugin' => 'uuid_lookup',
      'entity_type' => $field_config->getSetting('target_type'),
    ];

    // Default.
    $ref_process = [
      'plugin' => 'mukurtu_entity_lookup',
      'value_key' => 'uuid',
      'ignore_case' => TRUE,
      'entity_type' => $field_config->getSetting('target_type'),
    ];

    if ($ref_type == 'taxonomy_term') {
      $target_bundles = $field_config->getSetting('handler_settings')['target_bundles'] ?? [];
      $all_target_bundles = array_keys($target_bundles);
      $auto_create = $field_config->getSetting('handler_settings')['auto_create'];
      $auto_create_bundle = $field_config->getSetting('handler_settings')['auto_create_bundle'] ?? NULL;

      if (empty($auto_create_bundle)) {
        $auto_create_bundle = reset($all_target_bundles);
      }

      if ($auto_create) {
        $ref_process = [
          'plugin' => 'mukurtu_entity_generate',
          'value_key' => 'name',
          'bundle_key' => 'vid',
          'bundle' => $auto_create_bundle,
          'entity_type' => $field_config->getSetting('target_type'),
          'ignore_case' => TRUE,
        ];
      }
      else {
        $ref_process = [
          'plugin' => 'mukurtu_entity_lookup',
          'value_key' => 'name',
          'entity_type' => $field_config->getSetting('target_type'),
          'ignore_case' => TRUE,
        ];
        if (!empty($target_bundles)) {
          $ref_process['bundle_key'] = 'vid';
          $ref_process['bundle'] = $all_target_bundles;
        }
      }
    }

    if (in_array($ref_type, ['community', 'media', 'node', 'protocol'])) {
      $ref_process = [
        'plugin' => 'mukurtu_entity_lookup',
        'value_key' => $this->entityTypeManager->getDefinition($ref_type)->getKey('label'),
        'ignore_case' => TRUE,
        'entity_type' => $field_config->getSetting('target_type'),
      ];
    }

    // User ref. Only difference is value_key is set to 'name'.
    if ($ref_type == 'user') {
      $ref_process = [
        'plugin' => 'mukurtu_entity_lookup',
        'value_key' => 'name',
        'ignore_case' => TRUE,
        'entity_type' => $field_config->getSetting('target_type'),
      ];
    }

    $process[] = $ref_process;

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
  public function getFormatDescription(FieldDefinitionInterface $field_config, $field_property = NULL): TranslatableMarkup {
    $multiple = $this->isMultiple($field_config);
    $ref_type = $field_config->getSetting('target_type');

    if ($ref_type == 'user') {
      return $this->formatPlural($multiple, 'The username or user ID.', 'Usernames or User IDs, separated by your selected multi-value delimiter.');
    }

    if ($ref_type == 'taxonomy_term') {
      $auto_create = $field_config->getSetting('handler_settings')['auto_create'] ?? FALSE;
      if ($auto_create) {
        return $this->formatPlural($multiple, 'Taxonomy term name, ID, or UUID. The name must be exact and match only one term in that vocabulary. New terms will be created if they do not already exist.','Taxonomy term names, IDs, or UUIDs, separated by your selected multi-value delimiter. Each name must be exact and match only one term in that vocabulary. New terms will be created if they do not already exist.');
      }
      return $this->formatPlural($multiple, 'Taxonomy term name, ID, or UUID. The name must be exact and match only one term in that vocabulary.','Taxonomy term names, IDs, or UUIDs, separated by your selected multi-value delimiter. Each name must be exact and match only one term in that vocabulary.');
    }

    return $this->formatPlural($multiple, 'ID, UUID, or title of the reference. The title must be exact and match only one item.', 'IDs, UUIDs, or titles of the references, separated by your selected multi-value delimiter. Each title must be exact and match only one item.');
  }

}
