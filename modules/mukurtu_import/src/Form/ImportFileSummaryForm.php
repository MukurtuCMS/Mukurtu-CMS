<?php

namespace Drupal\mukurtu_import\Form;

use Drupal\mukurtu_import\Form\ImportBaseForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

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
    $metadataFiles = $this->getMetadataFiles();

    $form['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('File'),
        $this->t('Import Configuration'),
        '',
      ],
      '#attributes' => [
        'id' => 'import-configuration-summary'
      ],
      '#tabledrag' => [[
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'draggable-weight',
      ]],
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
      '#value' => $this->t('Import'),
    ];

    return $form;
  }

  protected function getImportConfigOptions($fid) {
    $file = $this->entityTypeManager->getStorage('file')->load($fid);

    // Custom is always available and is the default.
    $options = ['custom' => 'Custom Settings'];

    // Get the user's available import configs.
    $query = $this->entityTypeManager->getStorage('mukurtu_import_strategy')->getQuery();
    $result = $query->condition('uid', $this->currentUser()->id())->execute();

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
        $newConfig = $this->entityTypeManager->getStorage('mukurtu_import_strategy')->load($newConfigId);
        if ($newConfig) {
          $this->setImportConfig($fid, $newConfig);
        }
      }
    }

    // Update the field mapping message.
    $response = new AjaxResponse();

    $typeMessage = $this->getTypeSummaryMessage($fid);
    // Check how many fields for this file we have mapped with the selected process.
    $msg = $this->getMappedFieldsMessage($fid);
    $response->addCommand(new ReplaceCommand("#mapping-summary-{$fid}", "<div id=\"mapping-summary-{$fid}\"><div>{$typeMessage}</div><div>{$msg}</div></div>"));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
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
    $form_state->setRedirect('mukurtu_import.execute_import');
  }

}
