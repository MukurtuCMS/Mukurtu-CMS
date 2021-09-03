<?php

namespace Drupal\mukurtu_roundtrip\Form\MultiStepImport;

use Drupal\Core\Form\FormStateInterface;

class MukurtuImportBatchImportCompleteForm extends MukurtuImportFormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'mukurtu_import_batch_import_complete_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Submit for start new import button.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start a new import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Clear the importer workspace.
    $this->importer->reset();

    // Go back to the start form.
    $form_state->setRedirect('mukurtu_roundtrip.import_start');
  }

}
