<?php

namespace Drupal\mukurtu_roundtrip\Form\MultiStepImport;

use Drupal\Core\Form\FormStateInterface;

class MukurtuImportBatchValidationCompleteForm extends MukurtuImportFormBase {

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

    // Submit for import button.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    // Back button.
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#button_type' => 'primary',
      '#submit' => ['::submitFormBack'],
    ];
    return $form;
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
    //$form_state->setRedirect('mukurtu_roundtrip.import_validation_complete');
  }

  public function submitFormBack(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('mukurtu_roundtrip.import_upload_summary');
  }

}
