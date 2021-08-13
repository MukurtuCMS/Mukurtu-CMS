<?php

namespace Drupal\mukurtu_roundtrip\Form\MultiStepImport;

use Drupal\Core\Form\FormStateInterface;

class MukurtuImportUploadSummaryForm extends MukurtuImportFormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'mukurtu_import_upload_summary_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // These are the files to import.
    $files = $this->store->get('files_to_import');

    // We want to setup a DraggableListBuilder here with the
    // file and the import format selector.

    // Submit for validation button.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Validate'),
      '#button_type' => 'primary',
//      '#submit' => ['::submitFormValidateImport'],
/*       '#states' => [
        'visible' => [
          ':input[name="import_file[fids]"]' => ['filled' => TRUE],
        ],
      ], */
    );

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    //$form_state->setRedirect('mukurtu_import.import_upload_summary');
  }

}
