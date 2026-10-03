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
  field_types: ['entity_reference', 'mukurtu_entity_reference_role'],
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
  public function getSupportedProperties(FieldDefinitionInterface $field_definition): array {
    $is_single_media_ref = $field_definition->getType() === 'entity_reference'
      && $field_definition->getSetting('target_type') === 'media'
      && $field_definition->getFieldStorageDefinition()->getCardinality() === 1;

    if ($is_single_media_ref) {
      return [
        'target_id' => [
          'label' => sprintf('%s > %s', $field_definition->getLabel(), $this->t('File ID')),
          'description' => $this->getFormatDescription($field_definition, 'target_id'),
        ],
        'alt' => [
          'label' => sprintf('%s > %s', $field_definition->getLabel(), $this->t('Alternative text')),
          'description' => $this->getFormatDescription($field_definition, 'alt'),
        ],
      ];
    }

    // Reference-with-role fields keep their plain target (the names, as
    // before) and add a second column for the roles, in the same order.
    // ImportFormTrait offers the plain field because target_id isn't listed.
    if ($field_definition->getType() === 'mukurtu_entity_reference_role') {
      return [
        'role_target_id' => [
          'label' => sprintf('%s > %s', $field_definition->getLabel(), $this->t('Role')),
          'description' => $this->getFormatDescription($field_definition, 'role_target_id'),
        ],
      ];
    }

    return parent::getSupportedProperties($field_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getProcess(FieldDefinitionInterface $field_config, $source, $context = []): array {
    // Alt text for single-value media entity_reference is applied post-save
    // to the referenced media entity by ProtocolAwareEntityContent.
    if (($context['subfield'] ?? NULL) === 'alt'
      && $field_config->getSetting('target_type') === 'media') {
      return [['plugin' => 'get', 'source' => $source]];
    }

    $cardinality = $field_config->getFieldStorageDefinition()->getCardinality();
    $multivalue_delimiter = $context['multivalue_delimiter'] ?? self::MULTIVALUE_DELIMITER;

    // Roles stay as text, blanks included, so each keeps its position next
    // to its name. ProtocolAwareEntityContent pairs them with the names and
    // resolves them to role terms; looking them up here would drop the
    // blanks and shift every later role onto the wrong person.
    if (($context['subfield'] ?? NULL) === 'role_target_id') {
      $process = [];
      if ($cardinality == -1 || $cardinality > 1) {
        $process[] = [
          'plugin' => 'explode',
          'delimiter' => $multivalue_delimiter,
          'strict' => FALSE,
        ];
      }
      $process[] = ['plugin' => 'callback', 'callable' => 'trim'];
      $process[0]['source'] = $source;
      return $process;
    }
    $ref_type = $field_config->getSetting('target_type');
    $multiple = $cardinality == -1 || $cardinality > 1;
    $process = [];

    if ($multiple) {
      $process[] = [
        'plugin' => 'explode',
        'delimiter' => $multivalue_delimiter,
        'strict' => FALSE,
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
      $auto_create = $field_config->getSetting('handler_settings')['auto_create'] ?? FALSE;
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

    if (in_array($ref_type, ['community', 'media', 'node', 'protocol', 'multipage_item'])) {
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

    // User role ref (e.g. the 'roles' field on the user entity). Looked up by
    // machine name or label; the Administrator role is always rejected.
    if ($ref_type == 'user_role') {
      $ref_process = [
        'plugin' => 'mukurtu_role_lookup',
        'value_key' => 'label',
        'ignore_case' => TRUE,
        'entity_type' => 'user_role',
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
    // Custom entity types intentionally share the generic
    // mukurtu_entity_lookup path in getProcess() along with media, node,
    // taxonomy_term, user, and user_role.
    $custom_entity_types = \Drupal::service('mukurtu_core.roundtrip_entity_types')->getCustomEntityTypeIds();
    $supported_types = array_merge(['media', 'node', 'taxonomy_term', 'user', 'user_role'], $custom_entity_types);
    return in_array($refType, $supported_types);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatDescription(FieldDefinitionInterface $field_config, $field_property = NULL): TranslatableMarkup {
    $multiple = $this->isMultiple($field_config);
    $ref_type = $field_config->getSetting('target_type');

    if ($field_property === 'role_target_id') {
      return $this->t('The role for each name, in the same order as the names, separated by your selected multi-value delimiter. Leave a position empty for no role.');
    }

    if ($ref_type === 'media') {
      if ($field_property === 'alt') {
        return $this->t('The alt text for the image.');
      }
      return $this->t('The file ID or filename of the uploaded image.');
    }

    if ($ref_type == 'user') {
      return $this->formatPlural($multiple, 'The username or user ID.', 'Usernames or User IDs, separated by your selected multi-value delimiter.');
    }

    if ($ref_type == 'user_role') {
      return $this->formatPlural($multiple, 'A role machine name or label (e.g. mukurtu_manager or Mukurtu Manager). The Administrator role cannot be assigned via import.', 'Role machine names or labels, separated by your selected multi-value delimiter. The Administrator role cannot be assigned via import.');
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
