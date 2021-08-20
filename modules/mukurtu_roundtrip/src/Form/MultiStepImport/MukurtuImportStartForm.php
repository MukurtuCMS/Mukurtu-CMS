<?php

namespace Drupal\mukurtu_roundtrip\Form\MultiStepImport;

use Drupal\Core\Form\FormStateInterface;

class MukurtuImportStartForm extends MukurtuImportFormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'mukurtu_import_start_form';
  }

  /**
   * Return an array of the allowed extensions.
   */
  private function getValidExtensions() {
    // TODO: This should eventually be computed.
    $extensions = ['zip', 'json', 'csv'];
    return implode(" ", $extensions);
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildForm($form, $form_state);

    // Get the files if this is part of an existing import session.
    $files = $this->importer->getInputFiles();

    // File upload widget.
    $form['initial_import_files_helper_text'] = [
      '#plain_text' => $this->t("Upload your files or compressed archives below. Content import files (e.g., digital heritage) contained in compressed archives (e.g., Zip) must be at the top level of the archive. Binary files, such as media, can be at any level."),
    ];

    $form['initial_import_files'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Select files to import'),
      '#upload_location' => 'private://importfiles',
      '#multiple' => TRUE,
      '#default_value' => $files,
      '#upload_validators' => [
        'file_validate_extensions' => $this->getValidExtensions(),
      ],
      '#attributes' => [
        'name' => 'import_file_upload',
      ],
    ];

    // Next button.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next'),
      '#button_type' => 'primary',
      '#submit' => ['::submitForm'],
      '#states' => [
        'visible' => [
          ':input[name="initial_import_files[fids]"]' => ['filled' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Take the fids and process as needed (e.g., uncompress).
   */
/*   private function processUploadedFiles($files) {
    return $this->importer->setup($files);
  } */

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $files = $form_state->getValue('initial_import_files');
    $this->importer->setInputFiles($files);
    $this->importer->setup();

    $form_state->setRedirect('mukurtu_roundtrip.import_upload_summary');
  }

}
