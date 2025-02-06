<?php

namespace Drupal\mukurtu_import\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_import\Form\ImportBaseForm;

/**
 * Provides a Mukurtu Import form.
 */
class ImportFileSummaryForm extends ImportBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_import_import_file_summary';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->refreshMetadataFilesImportConfig();
    $metadataFileWeights = $this->getMetadataFileWeights();
    $metadataFiles = array_keys($metadataFileWeights);

    $form['all_files_ready'] = [
      '#type' => 'hidden',
      '#value' => $this->allFilesReady() ? "1" : "0",
      '#attributes' => [
        'name' => 'all-files-ready',
      ],
      '#prefix' => "<div id=\"all-files-ready\">",
      '#suffix' => "</div>",
    ];

    $form['table'] = [
      '#type' => 'table',
      '#caption' => $this->t('Files will be imported top to bottom. Select your field mapping for each file by using the "Customize Settings" button.'),
      '#header' => [
        $this->t('File'),
        $this->t('Import Configuration'),
        '',
        $this->t('Weight'),
      ],
      '#attributes' => [
        'id' => 'import-configuration-summary'
      ],
      '#tableselect' => FALSE,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'file-order-weight',
        ],
      ],
    ];

    foreach ($metadataFiles as $fid) {
      $config = $this->getImportConfig($fid);
      $configId = $config->id() ?? 'custom';
      $metadataFile = $this->entityTypeManager->getStorage('file')->load($fid);
      if (!$metadataFile) {
        continue;
      }

      $form['table'][$fid]['label'] = [
        '#markup' => $metadataFile->label(),
      ];

      $typeMessage = $this->getTypeSummaryMessage($fid);
      $mappedFieldMsg = $this->getMappedFieldsMessage($fid);
      $form['table'][$fid]['#attributes']['class'][] = 'draggable';
      $form['table'][$fid]['mapping'] = [
        '#type' => 'select',
        '#options' => $this->getImportConfigOptions($fid),
        '#default_value' => $configId,
        '#ajax' => [
          'callback' => [$this, 'mappingChangeAjaxCallback'],
          'event' => 'change',
        ],
        '#suffix' => "<div id=\"mapping-summary-{$fid}\"><div>{$typeMessage}</div><div>{$mappedFieldMsg}</div></div>",
      ];

      $form['table'][$fid]['edit'] = [
        '#type' => 'submit',
        '#name' => "edit-{$fid}",
        '#value' => $this->t('Customize Settings'),
        '#button_type' => 'primary',
        '#submit' => ['::defineCustomMapping'],
      ];

      $form['table'][$fid]['weight'] = [
        '#type' => 'weight',
        '#title_display' => 'invisible',
        '#title' => $this->t('Weight for @title', ['@title' => $metadataFile->getFilename()]),
        '#default_value' => $metadataFileWeights[$fid] ?? 0,
        '#attributes' => ['class' => ['file-order-weight']],
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#button_type' => 'primary',
      '#submit' => ['::submitBack'],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Review Import'),
      '#states' => [
        'disabled' => [
          ':input[name="all-files-ready"]' => ['value' => '0'],
        ],
        'enabled' => [
          ':input[name="all-files-ready"]' => ['value' => '1'],
        ],
      ],
    ];

    return $form;
  }

  protected function getImportConfigOptions($fid) {
    $file = $this->entityTypeManager->getStorage('file')->load($fid);

    // Custom is always available and is the default.
    $options = ['custom' => 'Custom Settings'];

    // Get the user's available import configs.
    $query = $this->entityTypeManager->getStorage('mukurtu_import_strategy')->getQuery();
    $result = $query
      ->condition('uid', $this->currentUser()->id())
      ->accessCheck(TRUE)
      ->execute();

    if (!empty($result)) {
      $configs = $this->entityTypeManager->getStorage('mukurtu_import_strategy')->loadMultiple($result);
      foreach ($configs as $config_id => $config) {
        if ($config->applies($file)) {
          $options[$config_id] = $config->getLabel();
        }
      }
    }

    return $options;
  }

  protected function getTypeSummaryMessage($fid) {
    // Get the import config for this file.
    $importConfig = $this->getImportConfig($fid);

    $entity_type_id = $importConfig->getTargetEntityTypeId();
    $entityLabel = $this->entityTypeManager->getDefinition($entity_type_id)->getLabel();
    $bundle = $importConfig->getTargetBundle();
    $bundleLabel = $this->entityBundleInfo->getBundleInfo($entity_type_id)[$bundle]['label'] ?? '';

    if ($bundle) {
      return $this->t('Importing @type: @bundle', ['@type' => $entityLabel, '@bundle' => $bundleLabel]);
    }

    return $this->t('Importing @type', ['@type' => $entityLabel]);
  }

  protected function getMappedFieldsMessage($fid) {
    // Get the import config for this file.
    $importConfig = $this->getImportConfig($fid);

    // Get the field mapping from the config.
    $process = $importConfig->getMapping();

    // Load the file and read the headers.
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    $fileHeaders = $this->getCSVHeaders($file);

    // Compare the import config to the headers.
    $mappingHeaders = array_column($process, 'source');
    $diff = array_diff($fileHeaders, $mappingHeaders);
    $mappedCount = count($fileHeaders) - count($diff);
    $targets = array_column($process, 'target');
    $targetCounts = array_count_values($targets);
    $ignored = $targetCounts[-1] ?? 0;

    if ($ignored) {
      return $this->t("@num of @total fields mapped, @ignored ignored", [
        '@num' => $mappedCount,
        '@total' => count($fileHeaders),
        '@ignored' => $ignored,
      ]);
    }

    return $this->t("@num of @total import fields mapped", [
      '@num' => $mappedCount,
      '@total' => count($fileHeaders),
    ]);
  }

  public function mappingChangeAjaxCallback(array &$form, FormStateInterface $form_state) {
    $element = $form_state->getTriggeringElement();
    $fid = $element['#parents'][1] ?? NULL;
    $table = $form_state->getValue('table');
    $newConfigId = $table[$fid]['mapping'] ?? 'custom';
    $currentConfig = $this->getImportConfig($fid);

    // If the config has actually changed, save the new change.
    if ($newConfigId != $currentConfig->id()) {
      // Switching from non-custom to custom is a special case.
      if ($newConfigId == 'custom') {
        // User switched from non-custom to custom. Duplicate the config
        // and blank out the title so it's a "fresh" custom config but
        // retains the field mapping/settings of the previously selected config.
        $newConfig = $currentConfig->createDuplicate();
        $newConfig->setLabel('');
        $this->setImportConfig($fid, $newConfig);
      } else {
        // A new import config has been selected.
        /** @var \Drupal\mukurtu_import\MukurtuImportStrategyInterface */
        $newConfig = $this->entityTypeManager->getStorage('mukurtu_import_strategy')->load($newConfigId);
        if ($newConfig) {
          $this->setImportConfig($fid, $newConfig);
        }
      }
    }

    $form_state->setRebuild(TRUE);

    // Update the field mapping message.
    $response = new AjaxResponse();

    $typeMessage = $this->getTypeSummaryMessage($fid);
    // Check how many fields for this file we have mapped with the selected process.
    $msg = $this->getMappedFieldsMessage($fid);
    $response->addCommand(new ReplaceCommand("#mapping-summary-{$fid}", "<div id=\"mapping-summary-{$fid}\"><div>{$typeMessage}</div><div>{$msg}</div></div>"));

    // Check if all files are valid enough to proceed with import.
    $form['all_files_ready']['#value'] = $this->allFilesReady() ? "1" : "0";
    $response->addCommand(new ReplaceCommand("#all-files-ready", $form['all_files_ready']));
    return $response;
  }

  /**
   * Check if all files are ready for import.
   *
   * @return boolean
   *  TRUE if all files are ready, FALSE otherwise.
   */
  protected function allFilesReady() {
    $unready_fids = $this->getUnreadyFileIds();
    return empty($unready_fids);
  }

  /**
   * Get an array of files that have config with no mapped fields.
   *
   * @return int[]
   *  The array of file IDs with incomplete config.
   */
  protected function getUnreadyFileIds() {
    $unready_fids = [];
    $metadataFiles = $this->getMetadataFiles();
    foreach ($metadataFiles as $fid) {
      $config = $this->getImportConfig($fid);
      /** @var \Drupal\file\FileInterface */
      $metadataFile = $this->entityTypeManager->getStorage('file')->load($fid);
      if (!$metadataFile) {
        continue;
      }

      if ($config->mappedFieldsCount($metadataFile) == 0) {
        $unready_fids[] = $fid;
      }
    }
    return $unready_fids;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    if ($triggering_element['#type'] == 'submit') {
      $callback = $triggering_element['#submit'][0] ?? NULL;
      // Let the user go back a step, even if the form is invalid.
      if ($callback == '::submitBack') {
        return;
      }

      // Let the user define custom mappings, even if the form is invalid.
      // Having undefined mappings is mostly the source of invalid state.
      if ($callback == '::defineCustomMapping') {
        return;
      }
    }

    foreach ($this->getUnreadyFileIds() as $unready_fid) {
      $form_state->setErrorByName("table][$unready_fid", $this->t("You must configure the import settings for this file."));
    }
  }

  public function submitBack(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('mukurtu_import.file_upload');
  }

  public function defineCustomMapping(array &$form, FormStateInterface $form_state) {
    // Get the file ID.
    $element = $form_state->getTriggeringElement();
    $fid = $element['#parents'][1] ?? NULL;
    if ($fid && is_numeric($fid)) {
      $form_state->setRedirect('mukurtu_import.custom_strategy_from_file_form', ['file' => $fid]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $table = $form_state->getValue('table');
    $weights = array_combine(array_keys($table), array_column($table, 'weight'));
    $this->setMetadataFileWeights($weights);

    $form_state->setRedirect('mukurtu_import.execute_import');
  }

}
