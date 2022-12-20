<?php

namespace Drupal\mukurtu_import\Form;

use Drupal\mukurtu_import\Form\ImportBaseForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\file\FileInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
/**
 * Provides a Mukurtu Import form.
 */
class CustomStrategyFromFileForm extends ImportBaseForm {
  protected $importConfig;
  protected $importConfigLoaded;
  protected $fieldDefinitions;

  /**
   * {@inheritdoc}
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_bundle_info){
    parent::__construct($temp_store_factory, $entity_type_manager, $entity_field_manager, $entity_bundle_info);

    // Initializing importConfig with a fresh import config just to make my
    // IDE happy... Loading the real config in buildForm where we have the
    // file id.
    $this->importConfig = $this->getImportConfig(-1);
    $this->importConfigLoaded = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_import_custom_strategy_from_file';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, FileInterface $file = NULL) {
    // Handle the initial loading of the import config for this file.
    if (!$this->importConfigLoaded) {
      $this->importConfig = $this->getImportConfig($file->id());
      $this->importConfigLoaded = TRUE;
    }

    if(!$file) {
      return $form;
    }

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
    $form['bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Sub-type'),
      '#options' => $this->getBundleOptions($entity_type_id),
      '#default_value' => $form_state->getValue('bundle') ?? $this->importConfig->getTargetBundle(),
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
    if ($this->importConfig->isNew()) {

    }

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
   * Get the options array for available target entity types.
   */
  protected function getEntityTypeIdOptions() {
    $definitons = $this->entityTypeManager->getDefinitions();
    $options = [];
    foreach (['node', 'media', 'community', 'protocol'] as $entity_type_id) {
      if (isset($definitons[$entity_type_id])) {
        $options[$entity_type_id] = $definitons[$entity_type_id]->getLabel();
      }
    }

    return $options;
  }

  protected function buildMappingTable(array &$form, FormStateInterface $form_state, FileInterface $file) {
    $userInput = $form_state->getUserInput();
    $entity_type_id = $form_state->getValue('entity_type_id') ?? ($userInput['entity_type_id'] ?? 'node');
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

      //$form['mappings'][$delta]['target'] = $this->buildTargetOptions($entity_type_id, $bundle);
      $form['mappings'][$delta]['target'] = [
        '#type' => 'select',
        '#options' => $this->buildTargetOptions($entity_type_id, $bundle),
        '#default_value' => $this->getAutoMappedTarget($header, $entity_type_id, $bundle),
        '#prefix' => "<div id=\"edit-mappings-{$delta}-target-options\">",
        '#suffix' => "</div>",
      ];

      //$form['mappings'][$delta]['target']['#prefix'] = "<div id=\"edit-mappings-{$delta}-target-options\">";
      //$form['mappings'][$delta]['target']['#suffix'] = "</div>";
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $element = $form_state->getTriggeringElement();
    // Skip validation if we're cancelling.
    if ($element['#parents'][0] == 'cancel') {
      return;
    }

    $mappings = $form_state->getValue('mappings');

    // Check for duplicate target mapping.
    $targets = array_column($mappings, 'target');
    $uniqueTargets = array_unique($targets);
    if (count($uniqueTargets) != count($targets)) {

      foreach (array_count_values($targets) as $dupeTarget => $count) {
        // Ignore the ignore field option, users can have duplicates of that.
        if ($count < 2 || $dupeTarget == -1) {
          continue;
        }

        foreach ($targets as $delta => $target) {
          if ($dupeTarget == $target) {
            $form_state->setError($form['mappings'][$delta], $this->t("Only a single source can be mapped to each target field."));
          }
        }
      }
    }
  }

  protected function getBundleOptions($entity_type_id) {
    $bundleInfoService = \Drupal::service('entity_type.bundle.info');
    $bundleInfo = $bundleInfoService->getAllBundleInfo();

    if (!isset($bundleInfo[$entity_type_id])) {
      return [-1 => $this->t('No sub-types available')];
    }

    $options = [-1 => $this->t('None: Base Fields Only')];
    foreach ($bundleInfo[$entity_type_id] as $bundle => $info) {
      $options[$bundle] = $info['label'] ?? $bundle;
    }
    return $options;
  }

  public function entityTypeChangeAjaxCallback(array &$form, FormStateInterface $form_state) {
    // Update the field mapping message.
    $response = new AjaxResponse();

    // Check how many fields for this file we have mapped with the selected process.
    $form['bundle']['#options'] = $this->getBundleOptions($form_state->getValue('entity_type_id'));
    $response->addCommand(new ReplaceCommand("#bundle-select", $form['bundle']));
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
    //$this->buildMappingTable($form, $form_state, $file);
    $headers = $this->getCSVHeaders($file);
    foreach ($headers as $delta => $header) {
      $form['mappings'][$delta]['target']['#options'] = $this->buildTargetOptions($entity_type_id, $bundle);
      $form['mappings'][$delta]['target']['#default_value'] = $this->getAutoMappedTarget($header, $entity_type_id, $bundle);
      $form['mappings'][$delta]['target']['#value'] = $this->getAutoMappedTarget($header, $entity_type_id, $bundle);
      // edit-mappings-0-target-options
      $response->addCommand(new ReplaceCommand("#edit-mappings-{$delta}-target-options", $form['mappings'][$delta]['target']));
    }
    //$response->addCommand(new ReplaceCommand("#import-field-mapping-config", $form['mappings']));
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
   * Get the field definitions for an entity type/bundle.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @return mixed
   *   The field definitions.
   */
  protected function getFieldDefinitions($entity_type_id, $bundle = NULL) {
    // Memoize the field defs.
    if (empty($this->fieldDefinitions[$entity_type_id][$bundle])) {
      $entityDefinition = $this->entityTypeManager->getDefinition($entity_type_id);
      $entityKeys = $entityDefinition->getKeys();
      $fieldDefs = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

      // Remove computed fields/fields that can't be targeted for import.
      foreach ($fieldDefs as $field_name => $fieldDef) {
        // Don't remove ID/UUID fields.
        if ($field_name == $entityKeys['id'] || $field_name == $entityKeys['uuid']) {
          continue;
        }

        // Remove computed and read-only fields.
        if ($fieldDef->isComputed() || $fieldDef->isReadOnly()) {
          unset($fieldDefs[$field_name]);
        }
      }
      $this->fieldDefinitions[$entity_type_id][$bundle] = $fieldDefs;
    }

    return $this->fieldDefinitions[$entity_type_id][$bundle];
  }

  /**
   * Compare field labels against a search string.
   *
   * @param string $needle
   *   The search term.
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @return string|null
   *   The field name of the match or NULL if no matches found.
   */
  protected function searchFieldLabels($needle, $entity_type_id, $bundle = NULL) {
    $fieldDefs = $this->getFieldDefinitions($entity_type_id, $bundle);
    foreach ($fieldDefs as $field_name => $field) {
      if ($needle == mb_strtolower($field->getLabel())) {
        return $field_name;
      }
    }
    return NULL;
  }

  /**
   * Build the mapper target options for a single source column.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @return mixed
   *   The select form element.
   */
  protected function buildTargetOptions($entity_type_id, $bundle = NULL) {
    $pceFields = $this->getFieldDefinitions('protocol_control');

    $options = [-1 => $this->t('Ignore - Do not import')];
    foreach ($this->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
      if ($field_definition->getType() == 'entity_reference') {
        if($field_definition->getSetting('target_type') == 'protocol_control') {
          // Split our protocol control reference into the individual sharing
          // setting/protocols components. We'll stitch them back together into
          // the protocol control entity in the destination plugin.
          $options["$field_name:field_sharing_setting"] = $pceFields['field_sharing_setting']->getLabel();
          $options["$field_name:field_protocols"] = $pceFields['field_protocols']->getLabel();
          continue;
        }
      }
      $options[$field_name] = $field_definition->getLabel();
    }

    return $options;
  }

  /**
   * Some basic logic to try and auto-map source to target.
   *
   * 1. Check for field name matches (case insensitive).
   * 2. Check for full field label matches (case insensitive).
   */
  protected function getAutoMappedTarget($source, $entity_type_id, $bundle = NULL) {
    $fieldDefs = $this->getFieldDefinitions($entity_type_id, $bundle);
    $configMapping = $this->importConfig ? $this->importConfig->getMapping() : [];

    // If the selected config has an existing valid mapping for this field,
    // it has precedence.
    foreach ($configMapping as $mapping) {
      // Break up any subfields.
      $subfields = explode(':', $mapping['target'], 2);
      $target = reset($subfields);

      // Checking if we have a mapping and the root of the target field exists.
      if ($mapping['source'] == $source && in_array($target, array_keys($fieldDefs))) {
        return $mapping['target'];
      }
    }

    $needle = mb_strtolower($source);

    // Check if we have a (case insensitive) field name match.
    if (isset($fieldDefs[$needle])) {
      return $needle;
    }

    // Check for field label matches.
    if ($fieldLabelMatch = $this->searchFieldLabels($needle, $entity_type_id, $bundle)) {
      return $fieldLabelMatch;
    }

    return -1;
  }

}
