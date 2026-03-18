<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\mukurtu_import\MukurtuImportStrategyInterface;

/**
 * Provides a Mukurtu Import form.
 */
class ImportFileSummaryForm extends ImportBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_import_import_file_summary';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->refreshMetadataFilesImportConfig();
    $metadata_file_weights = $this->getMetadataFileWeights();
    $metadata_files = array_keys($metadata_file_weights);

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

    foreach ($metadata_files as $fid) {
      $config = $this->getImportConfig($fid);
      $config_id = $config->id() ?? 'custom';
      $metadata_file = $this->entityTypeManager->getStorage('file')->load($fid);
      if (!$metadata_file instanceof FileInterface) {
        continue;
      }

      $form['table'][$fid]['label'] = [
        '#markup' => $metadata_file->label(),
      ];

      $type_message = $this->getTypeSummaryMessage($fid);
      $mapped_field_msg = $this->getMappedFieldsMessage($fid);
      $form['table'][$fid]['#attributes']['class'][] = 'draggable';
      $form['table'][$fid]['mapping'] = [
        '#type' => 'select',
        '#options' => $this->getImportConfigOptions($fid),
        '#default_value' => $config_id,
        '#ajax' => [
          'callback' => [$this, 'mappingChangeAjaxCallback'],
          'event' => 'change',
        ],
        '#suffix' => "<div id=\"mapping-summary-{$fid}\"><div>{$type_message}</div><div>{$mapped_field_msg}</div></div>",
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
        '#title' => $this->t('Weight for @title', ['@title' => $metadata_file->getFilename()]),
        '#default_value' => $metadata_file_weights[$fid] ?? 0,
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
    ];

    return $form;
  }

  /**
   * Gets available import configuration options for a file.
   *
   * Retrieves a list of import configurations that can be applied to the
   * specified file. Always includes a 'Custom Settings' option as the default.
   * Additionally loads all import strategy configurations owned by the current
   * user and filters them to only include strategies that apply to the file.
   *
   * @param int $fid
   *   The file ID to get import configuration options for.
   *
   * @return array
   *   An associative array of import configuration options where keys are
   *   configuration IDs (or 'custom' for custom settings) and values are
   *   human-readable labels.
   */
  protected function getImportConfigOptions($fid): array {
    // Custom is always available and is the default.
    $options = ['custom' => $this->t('Custom Settings')];

    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if (!$file instanceof FileInterface) {
      return $options;
    }

    // Get the user's available import configs.
    $storage = $this->entityTypeManager->getStorage('mukurtu_import_strategy');
    $result = $storage->getQuery()
      ->condition('uid', $this->currentUser()->id())
      ->accessCheck()
      ->execute();

    if (!empty($result)) {
      $configs = $storage->loadMultiple($result);
      foreach ($configs as $config_id => $config) {
        if (!$config instanceof MukurtuImportStrategyInterface || !$config->applies($file)) {
          continue;
        }
        $options[$config_id] = $config->getLabel();
      }
    }

    return $options;
  }

  /**
   * Gets a summary message describing the entity type and bundle imported.
   *
   * Generates a human-readable message indicating which entity type and bundle
   * will be created during the import process for the specified file.
   *
   * @param int $fid
   *   The file ID to get the import type summary for.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A translatable message describing the entity type and bundle being
   *   imported, or just the entity type if no bundle is specified.
   */
  protected function getTypeSummaryMessage($fid): TranslatableMarkup {
    // Get the import config for this file.
    $import_config = $this->getImportConfig($fid);
    $entity_type_id = $import_config->getTargetEntityTypeId();
    $entity_label = $this->entityTypeManager->getDefinition($entity_type_id)->getLabel();
    $bundle = $import_config->getTargetBundle();
    $bundle_label = $this->entityBundleInfo->getBundleInfo($entity_type_id)[$bundle]['label'] ?? '';

    if ($bundle) {
      return $this->t('Importing @type: @bundle', ['@type' => $entity_label, '@bundle' => $bundle_label]);
    }
    return $this->t('Importing @type', ['@type' => $entity_label]);
  }

  /**
   * Get a message describing how many fields were mapped for a file.
   *
   * @param int $fid
   *   File id for the metadat file.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A message describing how many fields were mapped for the file.
   */
  protected function getMappedFieldsMessage($fid): TranslatableMarkup {
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

  /**
   * Ajax callback for when the mapping configuration dropdown changes.
   *
   * Handles updating the import configuration when a user selects a different
   * mapping option for a file. Updates the mapping summary message and the
   * all files ready status via Ajax commands.
   *
   * @param array $form
   *   The complete form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An Ajax response containing commands to update the mapping summary
   *   and all files ready status.
   */
  public function mappingChangeAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $element = $form_state->getTriggeringElement();
    $fid = $element['#parents'][1] ?? NULL;
    $table = $form_state->getValue('table');
    $new_config_id = $table[$fid]['mapping'] ?? 'custom';
    $current_config = $this->getImportConfig($fid);

    // If the config has actually changed, save the new change.
    if ($new_config_id != $current_config->id()) {
      // Switching from non-custom to custom is a special case.
      if ($new_config_id == 'custom') {
        // User switched from non-custom to custom. Duplicate the config
        // and blank out the title so it's a "fresh" custom config but
        // retains the field mapping/settings of the previously selected config.
        $new_config = $current_config->createDuplicate();
        $new_config->setLabel('');
        $this->setImportConfig($fid, $new_config);
      }
      else {
        // A new import config has been selected.
        $new_config = $this->entityTypeManager->getStorage('mukurtu_import_strategy')->load($new_config_id);
        if ($new_config instanceof MukurtuImportStrategyInterface) {
          $this->setImportConfig($fid, $new_config);
        }
      }
    }

    $form_state->setRebuild();

    // Update the field mapping message.
    $response = new AjaxResponse();

    $type_message = $this->getTypeSummaryMessage($fid);
    // Check how many fields for this file we have mapped with the selected process.
    $msg = $this->getMappedFieldsMessage($fid);
    $response->addCommand(new ReplaceCommand("#mapping-summary-{$fid}", "<div id=\"mapping-summary-{$fid}\"><div>{$type_message}</div><div>{$msg}</div></div>"));

    return $response;
  }

  /**
   * Get an array of files that have config with no mapped fields.
   *
   * @return int[]
   *  The array of file IDs with incomplete config.
   */
  protected function getUnreadyFileIds(): array {
    $unready_fids = [];
    $metadata_files = $this->getMetadataFiles();
    foreach ($metadata_files as $fid) {
      $config = $this->getImportConfig($fid);
      $metadata_file = $this->entityTypeManager->getStorage('file')->load($fid);
      if (!$metadata_file instanceof FileInterface) {
        continue;
      }

      if ($config->mappedFieldsCount($metadata_file) == 0) {
        $unready_fids[] = $fid;
      }
    }
    return $unready_fids;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
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
      $form_state->setErrorByName("table][$unready_fid", $this->t("You must configure the import settings for %file.", ['%file' => $form['table'][$unready_fid]['label']['#markup']]));
    }
  }

  /**
   * Form submission handler for the "Back" button.
   *
   * Redirects the user back to the file upload step of the import workflow.
   *
   * @param array $form
   *   The complete form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitBack(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('mukurtu_import.file_upload');
  }

  /**
   * Form submission handler for the "Customize Settings" button.
   *
   * Redirects the user to the custom strategy configuration form for the
   * selected file. Extracts the file ID from the triggering element and
   * passes it to the custom strategy form route.
   *
   * @param array $form
   *   The complete form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function defineCustomMapping(array &$form, FormStateInterface $form_state): void {
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
    parent::submitForm($form, $form_state);
    $table = $form_state->getValue('table');
    $weights = array_combine(array_keys($table), array_column($table, 'weight'));
    $this->setMetadataFileWeights($weights);
    $form_state->setRedirect('mukurtu_import.execute_import');
  }

}
