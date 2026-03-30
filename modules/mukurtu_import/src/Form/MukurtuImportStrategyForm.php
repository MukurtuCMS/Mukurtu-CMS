<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding and editing Import Template entities.
 */
class MukurtuImportStrategyForm extends EntityForm {
  use ImportFormTrait;

  /**
   * Constructs a MukurtuImportStrategyForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityBundleInfo
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\mukurtu_import\MukurtuImportFieldProcessPluginManager $fieldProcessPluginManager
   *   The field process plugin manager.
   */
  public function __construct(
    protected EntityTypeBundleInfoInterface $entityBundleInfo,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected MukurtuImportFieldProcessPluginManager $fieldProcessPluginManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.mukurtu_import_field_process'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $form['#attached']['library'][] = 'mukurtu_import/strategy_form';

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the Import Template.'),
      '#required' => TRUE,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
      '#description' => $this->t('Enter a description for the Import Template.'),
    ];

    $form['target_entity_type_id'] = [
      '#type' => 'radios',
      '#options' => $this->getEntityTypeIdOptions(),
      '#title' => $this->t('Type'),
      '#default_value' => $form_state->getValue('target_entity_type_id') ?? $this->entity->get('target_entity_type_id') ?? 'node',
      '#description' => $this->t('Type of import.'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'entityTypeChangeAjaxCallback'],
        'event' => 'change',
      ],
    ];

    $entity_type_id = $form_state->getValue('target_entity_type_id') ?? $this->entity->get('target_entity_type_id') ?? 'node';
    $bundle_options = $this->getBundleOptions($entity_type_id);
    $bundle_keys = array_keys($bundle_options);
    $bundle = $form_state->getValue('target_bundle') ?? $this->entity->get('target_bundle') ?? reset($bundle_keys);
    $form['target_bundle'] = [
      '#type' => 'radios',
      '#title' => $this->t('Sub-type'),
      '#options' => $bundle_options,
      '#default_value' => $bundle,
      '#description' => $this->t('Optional Sub-type. When importing new content or media, they will be of this type if not specified in the import metadata.'),
      '#prefix' => "<div id=\"bundle-select\">",
      '#suffix' => "</div>",
      '#validated' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'bundleChangeAjaxCallback'],
        'event' => 'change',
      ],
    ];

    // Resolve the bundle for building target options. A value of -1 means
    // "Base Fields Only", which corresponds to NULL for field definitions.
    $resolved_bundle = ($bundle == -1) ? NULL : $bundle;

    $configuration = $this->entity->get('configuration') ?? [];
    $form['identifier_column'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Identifier Column'),
      '#description' => $this->t('Optional. Enter the column name to use as the unique identifier for each row. When set, this takes precedence over entity ID, UUID, and label columns for tracking rows in the import. Use this when importing entities without a natural label (e.g. paragraphs) so they can be referenced by other CSVs in the same import session.'),
      '#default_value' => $configuration['identifier_column'] ?? '',
    ];

    $this->buildMappingTable($form, $form_state, $entity_type_id, $resolved_bundle);

    // File settings for import (CSV parsing, delimiters, text format).
    $form['configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('File Settings'),
      '#tree' => TRUE,
    ];
    $form['configuration']['delimiter'] = [
      '#type' => 'textfield',
      '#size' => 5,
      '#maxlength' => 1,
      '#title' => $this->t('CSV Delimiter'),
      '#default_value' => $configuration['delimiter'] ?? ',',
    ];
    $form['configuration']['enclosure'] = [
      '#type' => 'textfield',
      '#size' => 5,
      '#maxlength' => 1,
      '#title' => $this->t('CSV Enclosure'),
      '#default_value' => $configuration['enclosure'] ?? '"',
    ];
    $form['configuration']['escape'] = [
      '#type' => 'textfield',
      '#size' => 5,
      '#maxlength' => 1,
      '#title' => $this->t('CSV Escape Character'),
      '#default_value' => $configuration['escape'] ?? '\\',
    ];
    $form['configuration']['multivalue_delimiter'] = [
      '#type' => 'textfield',
      '#size' => 5,
      '#title' => $this->t('Multi-value Delimiter'),
      '#default_value' => $configuration['multivalue_delimiter'] ?? ';',
    ];
    $form['configuration']['local_contexts_delimiter'] = [
      '#type' => 'textfield',
      '#size' => 5,
      '#title' => $this->t('Local Contexts Delimiter'),
      '#description' => $this->t('Delimiter used to separate the project name from the label/notice name in Local Contexts fields (e.g., "My Project > TK Attribution").'),
      '#default_value' => $configuration['local_contexts_delimiter'] ?? '>',
    ];
    $form['configuration']['default_format'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Default Text Format'),
      '#options' => $this->getTextFormatOptions(),
      '#default_value' => $configuration['default_format'] ?? MukurtuImportStrategy::DEFAULT_FORMAT,
    ];

    return $form;
  }

  /**
   * Builds the mapping table for source column to target field mapping.
   *
   * @param array $form
   *   The form array to add the mapping table to (passed by reference).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string|null $entity_type_id
   *   The target entity type ID.
   * @param string|null $bundle
   *   The target bundle (NULL for base fields only).
   */
  protected function buildMappingTable(array &$form, FormStateInterface $form_state, ?string $entity_type_id, ?string $bundle): void {
    $existing_mapping = $this->entity->getMapping();
    $target_options = $entity_type_id ? $this->buildTargetOptions($entity_type_id, $bundle) : [-1 => $this->t('Ignore - Do not import')];

    // Determine the number of mapping rows.
    $num_mappings = $form_state->get('num_mappings');
    if ($num_mappings === NULL) {
      $num_mappings = max(count($existing_mapping), 1);
      $form_state->set('num_mappings', $num_mappings);
    }

    $form['mapping'] = [
      '#type' => 'table',
      '#caption' => $this->t('Define source column to target field mappings.'),
      '#header' => [
        $this->t('Column Name'),
        $this->t('Target Field'),
        '',
      ],
      '#prefix' => "<div id=\"import-field-mapping-config\">",
      '#suffix' => "</div>",
    ];

    for ($delta = 0; $delta < $num_mappings; $delta++) {
      $default_source = $existing_mapping[$delta]['source'] ?? '';
      $default_target = $existing_mapping[$delta]['target'] ?? -1;

      $form['mapping'][$delta]['source'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Column Name'),
        '#title_display' => 'invisible',
        '#default_value' => $default_source,
        '#size' => 30,
        '#attributes' => ['class' => ['mapping-source-input']],
      ];

      $form['mapping'][$delta]['target'] = [
        '#type' => 'select',
        '#title' => $this->t('Target Field'),
        '#title_display' => 'invisible',
        '#options' => $target_options,
        '#default_value' => $default_target,
        '#validated' => TRUE,
      ];

      $form['mapping'][$delta]['remove'] = [
        '#delta' => $delta,
        '#name' => "mapping_{$delta}_remove_button",
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#validate' => [],
        '#submit' => ['::removeMappingSubmit'],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::mappingTableAjaxCallback',
          'wrapper' => 'import-field-mapping-config',
        ],
      ];
    }

    $form['add_mapping'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add mapping'),
      '#submit' => ['::addMappingCallback'],
      '#ajax' => [
        'callback' => '::mappingTableAjaxCallback',
        'wrapper' => 'import-field-mapping-config',
      ],
      '#limit_validation_errors' => [],
    ];
  }

  /**
   * Submit callback for the "Add mapping" button.
   */
  public function addMappingCallback(array &$form, FormStateInterface $form_state): void {
    $num_mappings = $form_state->get('num_mappings');
    $form_state->set('num_mappings', $num_mappings + 1);
    $form_state->setRebuild();
  }

  /**
   * Submit callback for the "Remove" button on a mapping row.
   */
  public function removeMappingSubmit(array &$form, FormStateInterface $form_state): void {
    $button = $form_state->getTriggeringElement();
    $delta = (int) $button['#delta'];

    // Remove the row from user input and re-index.
    $user_input = $form_state->getUserInput();
    $mapping_input = $user_input['mapping'] ?? [];
    unset($mapping_input[$delta]);
    $user_input['mapping'] = array_values($mapping_input);
    $form_state->setUserInput($user_input);

    // Decrement the row count (minimum 1).
    $num_mappings = $form_state->get('num_mappings');
    $form_state->set('num_mappings', max($num_mappings - 1, 1));
    $form_state->setRebuild();
  }

  /**
   * AJAX callback that returns the mapping table.
   */
  public function mappingTableAjaxCallback(array &$form, FormStateInterface $form_state): array {
    return $form['mapping'];
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state): void {
    // Filter out mapping rows with no source column name and re-index.
    $mapping = $form_state->getValue('mapping') ?? [];
    $mapping = array_filter($mapping, fn($row) => !empty(trim($row['source'] ?? '')));
    $form_state->setValue('mapping', array_values($mapping));

    // Normalize target_bundle: -1 means "Base Fields Only" = NULL.
    if ($form_state->getValue('target_bundle') == -1) {
      $form_state->setValue('target_bundle', NULL);
    }

    // Fold identifier_column into configuration.
    $configuration = $form_state->getValue('configuration') ?? [];
    $configuration['identifier_column'] = $form_state->getValue('identifier_column') ?: NULL;
    $form_state->setValue('configuration', $configuration);

    parent::copyFormValuesToEntity($entity, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    // Build a temporary entity with the submitted values so we can call
    // methods like getLabelSourceColumn() and getMediaSourceColumn() directly
    // without save/restore gymnastics.
    $entity = $this->buildEntity($form, $form_state);

    if (!$entity instanceof MukurtuImportStrategy) {
      return;
    }

    // When no Identifier Column is chosen, verify that toDefinition() will be
    // able to compute a unique source ID from the current mapping.
    if (!$entity->getIdentifierColumn()) {
      $entity_type_id = $entity->getTargetEntityTypeId();
      $entity_type_def = $this->entityTypeManager->getDefinition($entity_type_id);
      $mapped_targets = array_column($entity->getMapping(), 'target');

      $has_id = ($id_key = $entity_type_def->getKey('id')) && in_array($id_key, $mapped_targets);
      $has_uuid = ($uuid_key = $entity_type_def->getKey('uuid')) && in_array($uuid_key, $mapped_targets);
      $has_label = (bool) $entity->getLabelSourceColumn();
      $has_media_source = (bool) $entity->getMediaSourceColumn();

      if (!$has_id && !$has_uuid && !$has_label && !$has_media_source) {
        $form_state->setError($form['identifier_column'], $this->t('An Identifier Column is required. No ID, UUID, label, or compatible media source field is mapped, so the importer cannot uniquely identify rows. Set an Identifier Column above, or map one of the required fields.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new Import Template %label.', $message_args)
      : $this->t('Updated Import Template: %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

  /**
   * AJAX callback for entity type selection changes.
   */
  public function entityTypeChangeAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    // Update the bundle radios.
    $form['target_bundle']['#options'] = $this->getBundleOptions($form_state->getValue('target_entity_type_id'));
    $optionKeys = array_keys($form['target_bundle']['#options']);
    $default = reset($optionKeys);
    $form['target_bundle']['#default_value'] = $default;
    $form['target_bundle']['#value'] = $default;
    $form_state->setValue('target_bundle', $default);
    $response->addCommand(new ReplaceCommand("#bundle-select", $form['target_bundle']));

    // Update the mapping table target options.
    $response->addCommand(new ReplaceCommand("#import-field-mapping-config", $form['mapping']));

    return $response;
  }

  /**
   * AJAX callback for bundle selection changes.
   */
  public function bundleChangeAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand("#import-field-mapping-config", $form['mapping']));
    return $response;
  }

}
