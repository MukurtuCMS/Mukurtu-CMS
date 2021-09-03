<?php

namespace Drupal\mukurtu_roundtrip\Form\MultiStepImport;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;

class MukurtuImportBatchValidationCompleteForm extends MukurtuImportFormBase {

  protected $allValid = TRUE;

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'mukurtu_import_batch_validation_complete_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Build the report.
    $validationReport = $this->importer->getValidationReport();
    if (!empty($validationReport)) {
      $form['validation_report'] = $this->reportToTables($validationReport);
    } else {
      $this->allValid = FALSE;
    }

    // Submit for import button.
    $form['actions']['#type'] = 'actions';

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
      '#attributes' => [],
    ];

    if (!$this->allValid) {
      $form['actions']['submit']['#attributes']['disabled'] = 'disabled';
    }

    // Back button.
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#button_type' => 'primary',
      '#submit' => ['::submitFormBack'],
    ];
    return $form;
  }

  protected function reportToTables($report) {
    $tables = [];
    foreach ($report as $fid => $result) {
      $file = $this->fileStorage->load($fid);
      if (!empty($result)) {
        $table = [
          '#type' => 'table',
          '#caption' => $this->t('Validation results from file: @filename', ['@filename' => $file->getFileName()]),
          '#header' => [
            $this->t('Title'),
            $this->t('Validation Status'),
            $this->t("Actions"),
          ],
        ];

        // Do violations first.
        if (!empty($result['invalid'])) {
          // Flag that we have some validation violations.
          $this->allValid = FALSE;

          foreach ($result['invalid'] as $index => $invalid) {
            $entity = $invalid['entity'];
            $violations = $invalid['violations'];
            $violation_count = count($violations);

            $row = [];
            $row['title'] = [
              '#plain_text' => $entity->getTitle(),
            ];

            $violation_text = $violation_count > 1 ? $this->t('@num errors', ['@num' => $violation_count]) : $this->t('@num error', ['@num' => $violation_count]);
            $row['validation_status'] = [
              '#plain_text' => $violation_text,
            ];

            $link = Link::createFromRoute($this->t("View Details"), 'mukurtu_roundtrip.entity_validation_details', ['fid' => $fid, 'index' => $index]);
            $row['actions'] = $link->toRenderable();
            $table[] = $row;
          }
        }

        if (!empty($result['valid'])) {
          foreach ($result['valid'] as $index => $validImportEntity) {
            $entity = $validImportEntity['entity'];
            $row = [];
            $row['title'] = [
              '#plain_text' => $entity->getTitle(),
            ];
            $row['validation_status'] = [
              '#plain_text' => 'valid',
            ];

            $link = Link::createFromRoute($this->t("Preview"), 'mukurtu_roundtrip.entity_validation_details', ['fid' => $fid, 'index' => $index]);
            $row['actions'] = $link->toRenderable();
            $table[] = $row;
          }
        } else {
          // No valid entities.
          $this->allValid = FALSE;
        }
/*         foreach ($violations as $violation) {
          // Temp.
          $violationMessage = $violation['filename'] . ': ' . $violation['message'] . ' for ' . $violation['propertyPath'];
          $form['violations'][$fid][] = ['#plain_text' => $violationMessage];
        } */
        $tables[] = $table;
      }
    }

    return $tables;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $operations = $this->importer->getImportBatchOperations();
    $batch = [
      'title' => $this->t('Importing'),
      'operations' => $operations,
      'init_message' => $this->t('Reading the import files...'),
      'progress_message' => $this->t('Imported @current out of @total batches.'),
      'error_message' => $this->t('An error occurred during while batch importing the files.'),
    ];
    batch_set($batch);
    $form_state->setRedirect('mukurtu_roundtrip.import_complete');
  }

  public function submitFormBack(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('mukurtu_roundtrip.import_upload_summary');
  }

}
