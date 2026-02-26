<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\file\FileInterface;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\mukurtu_import\MukurtuImportStrategyInterface;

/**
 * Provides a Mukurtu Import form.
 */
class CustomStrategyFromFileForm extends ImportBaseForm {

  /**
   * Import configuration object managed by this form.
   *
   * @var \Drupal\mukurtu_import\MukurtuImportStrategyInterface
   */
  protected MukurtuImportStrategyInterface $importConfig;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_import_custom_strategy_from_file';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?FileInterface $file = NULL): array {
    $form = parent::buildForm($form, $form_state);
    if (!$file instanceof FileInterface) {
      return $form;
    }
    $this->importConfig = $this->getImportConfig($file->id());

    $form['fid'] = [
      '#type' => 'value',
      '#value' => $file->id(),
    ];

    $form['entity_type_id'] = [
      '#type' => 'radios',
      '#options' => $this->getEntityTypeIdOptions(),
      '#title' => $this->t('Type'),
      '#default_value' => $form_state->getValue('entity_type_id') ?? $this->importConfig->getTargetEntityTypeId(),
      '#description' => $this->t('Type of import.'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'entityTypeChangeAjaxCallback'],
        'event' => 'change',
      ],
    ];

    $entity_type_id = $form_state->getValue('entity_type_id') ?? $this->importConfig->getTargetEntityTypeId();
    $bundle_options = $this->getBundleOptions($entity_type_id);
    $bundle_keys = array_keys($bundle_options);
    $form['bundle'] = [
      '#type' => 'radios',
      '#title' => $this->t('Sub-type'),
      '#options' => $bundle_options,
      '#default_value' => $form_state->getValue('bundle') ?? $this->importConfig->getTargetBundle() ?? reset($bundle_keys),
      '#description' => $this->t('Optional Sub-type. When importing new content or media, they will be of this type if not specified in the import metadata.'),
      '#prefix' => "<div id=\"bundle-select\">",
      '#suffix' => "</div>",
      '#validated' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'bundleChangeAjaxCallback'],
        'event' => 'change',
      ],
    ];

    $this->buildMappingTable($form, $form_state, $file);

    // File options for import like multivalue delimiter, enclosure, etc.
    $form['file_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('File Settings'),
    ];
    $form['file_settings']['delimiter'] = [
      '#type' => 'textfield',
      '#size' => 5,
      '#maxlength' => 1,
      '#title' => $this->t('CSV Delimiter'),
      '#default_value' => $this->importConfig->getConfig('delimiter') ?? ',',
    ];
    $form['file_settings']['enclosure'] = [
      '#type' => 'textfield',
      '#size' => 5,
      '#maxlength' => 1,
      '#title' => $this->t('CSV Enclosure'),
      '#default_value' => $this->importConfig->getConfig('enclosure') ?? '"',
    ];
    $form['file_settings']['escape'] = [
      '#type' => 'textfield',
      '#size' => 5,
      '#maxlength' => 1,
      '#title' => $this->t('CSV Escape Character'),
      '#default_value' => $this->importConfig->getConfig('escape') ?? '\\',
    ];
    $form['file_settings']['multivalue_delimiter'] = [
      '#type' => 'textfield',
      '#size' => 5,
      '#title' => $this->t('Multi-value Delimiter'),
      '#default_value' => $this->importConfig->getConfig('multivalue_delimiter') ?? ';',
    ];
    $header_options = ['' => $this->t('- None -')];
    foreach ($this->getCSVHeaders($file) as $header) {
      $header_options[$header] = $header;
    }
    $form['file_settings']['identifier_column'] = [
      '#type' => 'select',
      '#title' => $this->t('Identifier Column'),
      '#description' => $this->t('Optional. Select a column to use as the unique identifier for each row. When set, this takes precedence over entity ID, UUID, and label columns for tracking rows in the import. Use this when importing entities without a natural label (e.g. paragraphs) so they can be referenced by other CSVs in the same import session.'),
      '#options' => $header_options,
      '#default_value' => $this->importConfig->getConfig('identifier_column') ?? '',
    ];
    $form['file_settings']['default_format'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Default Text Format'),
      '#options' => $this->getTextFormatOptions(),
      '#default_value' => $this->importConfig->getConfig('default_format') ?? MukurtuImportStrategy::DEFAULT_FORMAT,
    ];

    // Provide an option to save this config for reuse.
    $form['import_config'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Import Configuration Template'),
    ];
    $form['import_config']['config_save'] = [
      '#type' => 'checkbox',
      '#default_value' => FALSE,
      '#title' => $this->importConfig->isNew() ? $this->t('Save this import configuration as a template for future imports.') : $this->t('Save the changes to this existing template.'),
      '#attributes' => [
        'name' => 'config_save',
      ],
    ];

    $form['import_config']['config_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $this->importConfig->isNew() ? '' : $this->importConfig->getLabel(),
      '#states' => [
        'visible' => [
          ':input[name="config_save"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#button_type' => 'primary',
      '#submit' => ['::submitCancel'],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * Get the options array for available text formats.
   *
   * @return array
   *   An array of text format labels indexed by format ID.
   */
  protected function getTextFormatOptions(): array {
    $formats = filter_formats();
    return array_map(function($format) {
      return $format->label();
    }, $formats);
  }

  /**
   * Get the options array for available target entity types.
   */
  protected function getEntityTypeIdOptions() {
    $definitions = $this->entityTypeManager->getDefinitions();
    $options = [];
    foreach (['node', 'media', 'community', 'protocol', 'paragraph', 'multipage_item'] as $entity_type_id) {
      if (isset($definitions[$entity_type_id]) && $this->userCanCreateAnyBundleForEntityType($entity_type_id)) {
        $options[$entity_type_id] = $definitions[$entity_type_id]->getLabel();

        // Override certain labels, including paragraphs.
        if ($entity_type_id === 'paragraph') {
          $options[$entity_type_id] = $this->t('Compound Types (paragraphs)');
        }
      }
    }

    return $options;
  }

  /**
   * Builds the mapping table form element for CSV column to field mapping.
   *
   * This method creates a table form element that allows users to map columns
   * from the uploaded CSV file to target entity fields. The available target
   * fields depend on the selected entity type and bundle.
   *
   * The method:
   * - Reads the CSV headers from the uploaded file
   * - Creates a row for each CSV column
   * - Provides a select dropdown for each column to choose the target field
   * - Attempts to auto-map columns to fields based on label/name matching
   * - Uses AJAX wrapper divs to allow dynamic updates when entity type/bundle
   * changes
   *
   * @param array $form
   *   The form array to add the mapping table to (passed by reference).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state containing user selections.
   * @param \Drupal\file\FileInterface $file
   *   The uploaded CSV file to map columns from.
   *
   * @return array
   *   The modified form array with the mapping table added.
   */
  protected function buildMappingTable(array &$form, FormStateInterface $form_state, FileInterface $file): array {
    $userInput = $form_state->getUserInput();
    $entity_type_id = $form_state->getValue('entity_type_id') ?? ($userInput['entity_type_id'] ?? ($form['entity_type_id']['#default_value'] ?? 'node'));
    $bundle = $form_state->getValue('bundle') ?? ($userInput['bundle'] ?? $this->importConfig->getTargetBundle());
    $headers = $this->getCSVHeaders($file);
    if (empty($headers)) {
      return $form;
    }
    $form['mappings'] = [];
    $form['mappings'] = [
      '#type' => 'table',
      '#caption' => $this->t('Define custom source/target mappings for file %file', ['%file' => $file->getFilename()]),
      '#header' => [
        $this->t('Column Name'),
        $this->t('Target Field'),
        '',
      ],
      '#prefix' => "<div id=\"import-field-mapping-config\">",
      '#suffix' => "</div>",
    ];

    foreach ($headers as $delta => $header) {
      $form['mappings'][$delta]['source_title'] = [
        '#plain_text' => $header,
      ];
      $form['mappings'][$delta]['target'] = [
        '#type' => 'select',
        '#options' => $this->buildTargetOptions($entity_type_id, $bundle),
        '#default_value' => $this->importConfig->getMappedTarget($header) ?? $this->getAutoMappedTarget($header, $entity_type_id, $bundle),
        '#prefix' => "<div id=\"edit-mappings-{$delta}-target-options\">",
        '#suffix' => "</div>",
        '#validated' => TRUE,
      ];

      $form['mappings'][$delta]['source'] = [
        '#type' => 'value',
        '#value' => $header,
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $element = $form_state->getTriggeringElement();
    // Skip validation if we're cancelling.
    if ($element['#parents'][0] === 'cancel') {
      return;
    }

    $mappings = $form_state->getValue('mappings');

    // Check for duplicate target mapping.
    $targets = array_column($mappings, 'target');
    $unique_targets = array_unique($targets);
    if (count($unique_targets) !== count($targets)) {

      foreach (array_count_values($targets) as $dupe_target => $count) {
        // Ignore the ignore field option, users can have duplicates of that.
        if ($count < 2 || $dupe_target == -1) {
          continue;
        }

        foreach ($targets as $delta => $target) {
          if ($dupe_target == $target) {
            $form_state->setError($form['mappings'][$delta], $this->t("Only a single source can be mapped to each target field."));
          }
        }
      }
    }
  }

  /**
   * Gets the available bundle options for a given entity type.
   *
   * This method retrieves all bundles for the specified entity type that the
   * current user has permission to create. It provides a select list of
   * available sub-types/bundles for the import form.
   *
   * If there is only one bundle available, the "None: Base Fields Only" option
   * is excluded from the returned options array.
   *
   * @param string $entity_type_id
   *   The entity type ID to get bundles for (e.g., 'node', 'media', etc.).
   *
   * @return array
   *   An associative array of bundle options where keys are bundle machine
   *   names (or -1 for base fields only) and values are bundle labels. Returns
   *   an array with a single "No sub-types available" option if no bundles
   *   exist for the entity type.
   */
  protected function getBundleOptions($entity_type_id): array {
    $bundle_info = $this->entityBundleInfo->getAllBundleInfo();

    if (!isset($bundle_info[$entity_type_id])) {
      return [-1 => $this->t('No sub-types available')];
    }

    // Don't provide the base fields option if there is only one valid bundle.
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
   * AJAX callback handler for entity type selection changes.
   *
   * This method handles the dynamic form updates when the user changes the
   * entity type selection (e.g., from 'node' to 'media').
   *
   * @param array $form
   *   The complete form array (passed by reference).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state containing user input and values.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response object containing ReplaceCommand operations to update
   *   the bundle selector and all field mapping dropdowns with appropriate
   *   options for the newly selected entity type.
   */
  public function entityTypeChangeAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    // Update the field mapping message.
    $response = new AjaxResponse();

    // Check how many fields for this file we have mapped with the selected process.
    $form['bundle']['#options'] = $this->getBundleOptions($form_state->getValue('entity_type_id'));
    $optionKeys = array_keys($form['bundle']['#options']);
    $default = reset($optionKeys);
    $bundle = $default == -1 ? NULL : $default;
    $form['bundle']['#default_value'] = $default;
    $form['bundle']['#value'] = $default;
    $form['bundle'][$default]['#value'] = $default;
    $form['bundle'][$default]['#default_value'] = $default;
    $form_state->setValue('bundle', $default);
    $response->addCommand(new ReplaceCommand("#bundle-select", $form['bundle']));

    // Changing the entity type will reset the bundle selector, so refresh the
    // field mapper based on the new default bundle.
    $form_state->setRebuild(TRUE);
    $fid = $form_state->getValue('fid');
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    $userInput = $form_state->getUserInput();

    $userInput['bundle'] = $default;
    $entity_type_id = $form_state->getValue('entity_type_id') ?? ($userInput['entity_type_id'] ?? 'node');
    $headers = $this->getCSVHeaders($file);
    foreach ($headers as $delta => $header) {
      $form['mappings'][$delta]['target']['#options'] = $this->buildTargetOptions($entity_type_id, $bundle);
      $form['mappings'][$delta]['target']['#default_value'] = $this->getAutoMappedTarget($header, $entity_type_id, $bundle);
      $form['mappings'][$delta]['target']['#value'] = $this->getAutoMappedTarget($header, $entity_type_id, $bundle);
      $form['mappings']['#value'][$delta]['target'] = $form['mappings'][$delta]['target']['#default_value'];
      $userInput['mappings'][$delta]['target'] = $form['mappings'][$delta]['target']['#default_value'];
      $response->addCommand(new ReplaceCommand("#edit-mappings-{$delta}-target-options", $form['mappings'][$delta]['target']));
    }
    $form_state->setValue('mappings', $userInput['mappings']);
    $form_state->setUserInput($userInput);
    return $response;
  }

  public function bundleChangeAjaxCallback(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    $fid = $form_state->getValue('fid');
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    $userInput = $form_state->getUserInput();
    $entity_type_id = $form_state->getValue('entity_type_id') ?? ($userInput['entity_type_id'] ?? 'node');
    $bundle = $form_state->getValue('bundle') ?? ($userInput['bundle'] ?? NULL);
    $response = new AjaxResponse();
    $headers = $this->getCSVHeaders($file);
    foreach ($headers as $delta => $header) {
      $form['mappings'][$delta]['target']['#options'] = $this->buildTargetOptions($entity_type_id, $bundle);
      $form['mappings'][$delta]['target']['#default_value'] = $this->getAutoMappedTarget($header, $entity_type_id, $bundle);
      $form['mappings'][$delta]['target']['#value'] = $this->getAutoMappedTarget($header, $entity_type_id, $bundle);
      $form['mappings']['#value'][$delta]['target'] = $form['mappings'][$delta]['target']['#default_value'];
      $userInput['mappings'][$delta]['target'] = $form['mappings'][$delta]['target']['#default_value'];
      $response->addCommand(new ReplaceCommand("#edit-mappings-{$delta}-target-options", $form['mappings'][$delta]['target']));
    }
    $form_state->setValue('mappings', $userInput['mappings']);
    return $response;
  }

  public function submitCancel(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('mukurtu_import.import_files');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save config.
    $fid = $form_state->getValue('fid');

    $entity_type_id = $form_state->getValue('entity_type_id');
    $bundle = $form_state->getValue('bundle') == -1 ? NULL : $form_state->getValue('bundle');

    $this->importConfig->setTargetEntityTypeId($entity_type_id);
    $this->importConfig->setTargetBundle($bundle);
    $this->importConfig->setMapping($form_state->getValue('mappings'));
    $this->importConfig->setConfig('delimiter', $form_state->getValue('delimiter'));
    $this->importConfig->setConfig('enclosure', $form_state->getValue('enclosure'));
    $this->importConfig->setConfig('escape', $form_state->getValue('escape'));
    $this->importConfig->setConfig('multivalue_delimiter', $form_state->getValue('multivalue_delimiter'));
    $this->importConfig->setConfig('default_format', $form_state->getValue('default_format'));
    $this->importConfig->setConfig('identifier_column', $form_state->getValue('identifier_column') ?: NULL);

    if ($form_state->getValue('config_save')) {
      $userProvidedLabel = $form_state->getValue('config_title');
      if (trim($userProvidedLabel) != '') {
        $this->importConfig->setLabel($userProvidedLabel);
      }
      $this->importConfig->setOwnerId($this->currentUser()->id());
      $this->importConfig->save();
    }
    $this->setImportConfig($fid, $this->importConfig);


    // Go back to the file summary form.
    $form_state->setRedirect('mukurtu_import.import_files');
  }

  /**
   * Compare field labels against a search string.
   *
   * @param string $needle
   *   The search term.
   * @param string $entity_type_id
   *   The entity type id.
   * @param string|null $bundle
   *   The bundle.
   * @return string|null
   *   The field name of the match or NULL if no matches found.
   */
  protected function searchFieldLabels(string $needle, string $entity_type_id, ?string $bundle = NULL): ?string {
    $field_defs = $this->getFieldDefinitions($entity_type_id, $bundle);
    $matching_fields = array_filter($field_defs, function($field) use ($needle) {
      return $needle == mb_strtolower((string) $field->getLabel());
    });

    // If there are multiple matches, return the first bundle specific match.
    if (count($matching_fields) > 1) {
      foreach ($matching_fields as $matched_field_name => $matched_field) {
        if ($matched_field->getTargetBundle()) {
          return $matched_field_name;
        }
      }
    }

    // If all are base fields, return the first.
    if (count($matching_fields) >= 1) {
      $field_names = array_keys($matching_fields);
      return reset($field_names);
    }

    return NULL;
  }

  /**
   * Search for a cultural_protocol field for a given entity/bundle.
   */
  protected function getProtocolField($entity_type_id, $bundle = NULL) {
    $field_defs = $this->getFieldDefinitions($entity_type_id, $bundle);
    $protocol_fields = [];
    foreach ($field_defs as $field_name => $field) {
      if ($field->getType() == 'cultural_protocol') {
        $protocol_fields[$field_name] = $field_name;
      }
    }

    if (count($protocol_fields) == 1) {
      return reset($protocol_fields);
    }

    if (count($protocol_fields) > 1) {
      // If there are multiple protocol fields for some reason, but ours is
      // present, default to that.
      if (isset($protocol_fields['field_cultural_protocols'])) {
        return $protocol_fields['field_cultural_protocols'];
      }

      // Otherwise use the first one.
      return reset($protocol_fields);
    }

    return NULL;
  }

  /**
   * Some basic logic to try and auto-map source to target.
   *
   * 1. Check for full field label matches (case insensitive).
   * 2. Check for field name matches (case insensitive).
   */
  protected function getAutoMappedTarget($source, $entity_type_id, $bundle = NULL) {
    $field_defs = $this->getFieldDefinitions($entity_type_id, $bundle);
    $config_mapping = $this->importConfig ? $this->importConfig->getMapping() : [];

    // If the selected config has an existing valid mapping for this field,
    // it has precedence.
    foreach ($config_mapping as $mapping) {
      // Break up any subfields.
      $subfields = explode('/', $mapping['target'], 2);
      $target = reset($subfields);

      // Checking if we have a mapping and the root of the target field exists.
      if ($mapping['source'] == $source && in_array($target, array_keys($field_defs))) {
        return $mapping['target'];
      }
    }

    $needle = mb_strtolower($source);

    // Check if any field has a property, which our import field process plugins
    // support, matching the source label.
    foreach ($field_defs as $field_name => $field_definition) {
      $plugin = $this->fieldProcessPluginManager->getInstance(['field_definition' => $field_definition]);
      $supported_properties = $plugin->getSupportedProperties($field_definition);

      foreach ($supported_properties as $property_name => $property_info) {
        if ($needle == mb_strtolower($property_info['label'])) {
          return "{$field_name}/{$property_name}";
        }
      }
    }

    // Similarly, resolve language/langcode here. Our field_language in English
    // has a "Language" field label which keeps matching to the locale langcode.


    // Check for field label matches.
    if ($fieldLabelMatch = $this->searchFieldLabels($needle, $entity_type_id, $bundle)) {
      return $fieldLabelMatch;
    }

    // Check if we have a (case insensitive) field name match.
    if (isset($field_defs[$needle])) {
      return $needle;
    }

    return -1;
  }

}
