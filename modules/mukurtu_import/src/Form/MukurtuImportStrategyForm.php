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
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding and editing Import Configuration Template entities.
 */
class MukurtuImportStrategyForm extends EntityForm {

  /**
   * Memoized field definitions.
   *
   * @var array
   */
  protected array $fieldDefinitions = [];

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

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the Import Configuration template.'),
      '#required' => TRUE,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
      '#description' => $this->t('Enter a description for the Import Configuration template.'),
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

    $entity_type_id = $form_state->getValue('target_entity_type_id') ?? $this->entity->get('target_entity_type_id');
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

    $this->buildMappingTable($form, $form_state, $entity_type_id, $resolved_bundle);

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
   * Get the options array for available target entity types.
   *
   * @return array
   *   An associative array of entity type IDs to labels, filtered by
   *   the current user's create access.
   */
  protected function getEntityTypeIdOptions(): array {
    $definitions = $this->entityTypeManager->getDefinitions();
    $options = [];
    foreach (['node', 'media', 'community', 'protocol', 'paragraph', 'multipage_item'] as $entity_type_id) {
      if (isset($definitions[$entity_type_id]) && $this->userCanCreateAnyBundleForEntityType($entity_type_id)) {
        $options[$entity_type_id] = $definitions[$entity_type_id]->getLabel();

        if ($entity_type_id === 'paragraph') {
          $options[$entity_type_id] = $this->t('Compound Types (paragraphs)');
        }
      }
    }

    return $options;
  }

  /**
   * Gets the available bundle options for a given entity type.
   *
   * @param string|null $entity_type_id
   *   The entity type ID to get bundles for.
   *
   * @return array
   *   An associative array of bundle options filtered by user access.
   */
  protected function getBundleOptions(?string $entity_type_id): array {
    $bundle_info = $this->entityBundleInfo->getAllBundleInfo();

    if (!isset($bundle_info[$entity_type_id])) {
      return [-1 => $this->t('No sub-types available')];
    }

    $options = [];
    if (count($bundle_info[$entity_type_id]) > 1) {
      $options = [-1 => $this->t('None: Base Fields Only')];
    }

    foreach ($bundle_info[$entity_type_id] as $bundle => $info) {
      if ($this->userCanCreateEntity($entity_type_id, $bundle)) {
        $options[$bundle] = $info['label'] ?? $bundle;
      }
    }
    return $options;
  }

  /**
   * Build the target field options for the mapping select elements.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|null $bundle
   *   The bundle.
   *
   * @return array
   *   An associative array of field names/subfields to labels.
   */
  protected function buildTargetOptions(string $entity_type_id, ?string $bundle = NULL): array {
    $entity_definition = $this->entityTypeManager->getDefinition($entity_type_id);
    $entity_keys = $entity_definition->getKeys();

    $options = [-1 => $this->t('Ignore - Do not import')];
    foreach ($this->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
      $plugin = $this->fieldProcessPluginManager->getInstance(['field_definition' => $field_definition]);
      $supported_properties = $plugin->getSupportedProperties($field_definition);

      if (!empty($supported_properties)) {
        foreach ($supported_properties as $property_name => $property_info) {
          $options["{$field_name}/{$property_name}"] = $property_info['label'];
        }
      }
      else {
        $options[$field_name] = $field_definition->getLabel();
      }
    }

    // Disambiguate the Language field from the langcode base field.
    if (isset($options[$entity_keys['langcode']])) {
      $options[$entity_keys['langcode']] .= $this->t(' (langcode)');
    }

    return $options;
  }

  /**
   * Checks if a user has permission to create an entity of a specific type and bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|null $bundle
   *   The bundle.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account. Defaults to the current user.
   *
   * @return bool
   *   TRUE if the user has access.
   */
  protected function userCanCreateEntity(string $entity_type_id, ?string $bundle = NULL, ?AccountInterface $account = NULL): bool {
    if (!$account) {
      $account = $this->currentUser();
    }
    return $this->entityTypeManager->getAccessControlHandler($entity_type_id)->createAccess($bundle, $account);
  }

  /**
   * Checks if a user can create any bundle of a specific entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account. Defaults to the current user.
   *
   * @return bool
   *   TRUE if the user has access to create at least one bundle.
   */
  protected function userCanCreateAnyBundleForEntityType(string $entity_type_id, ?AccountInterface $account = NULL): bool {
    if (!$account) {
      $account = $this->currentUser();
    }

    $bundle_info = $this->entityBundleInfo->getAllBundleInfo();
    if (!empty($bundle_info[$entity_type_id])) {
      foreach ($bundle_info[$entity_type_id] as $bundle_id => $info) {
        if ($this->userCanCreateEntity($entity_type_id, $bundle_id, $account)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Get the field definitions for an entity type/bundle.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string|null $bundle
   *   The bundle.
   *
   * @return array
   *   The field definitions.
   */
  protected function getFieldDefinitions(string $entity_type_id, ?string $bundle = NULL): array {
    if (empty($this->fieldDefinitions[$entity_type_id][$bundle])) {
      $entityDefinition = $this->entityTypeManager->getDefinition($entity_type_id);
      $entityKeys = $entityDefinition->getKeys();
      $fieldDefs = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

      foreach ($fieldDefs as $field_name => $fieldDef) {
        if ($field_name === $entityKeys['id'] || $field_name === $entityKeys['uuid']) {
          continue;
        }

        // Remove the revision log message as a valid target.
        if ($field_name === 'revision_log') {
          unset($fieldDefs[$field_name]);
        }

        // Remove unwanted 'behavior_settings' paragraph base field.
        if ($entity_type_id === 'paragraph' && $field_name === 'behavior_settings') {
          unset($fieldDefs[$field_name]);
        }

        if ($fieldDef->isComputed() || $fieldDef->isReadOnly()) {
          unset($fieldDefs[$field_name]);
        }
      }
      $this->fieldDefinitions[$entity_type_id][$bundle] = $fieldDefs;
    }

    return $this->fieldDefinitions[$entity_type_id][$bundle];
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

    parent::copyFormValuesToEntity($entity, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new Import Configuration Template %label.', $message_args)
      : $this->t('Updated Import Configuration Template: %label.', $message_args);
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
