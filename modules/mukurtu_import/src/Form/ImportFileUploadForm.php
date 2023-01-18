<?php

namespace Drupal\mukurtu_import\Form;

use Drupal\mukurtu_import\Form\ImportBaseForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\file\Element\ManagedFile;
use Exception;

/**
 * Provides a Mukurtu Import form.
 */
class ImportFileUploadForm extends ImportBaseForm implements TrustedCallbackInterface {

  public static function trustedCallbacks() {
    return [
      'processManagedFile',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_import_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $metadataFiles = $this->getMetadataFiles();

    $form['metadata_files'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Metadata Files'),
      '#process' => [[static::class, 'processManagedFile']],
      '#multiple' => TRUE,
      '#default_value' => $metadataFiles,
      '#upload_location' => 'private://importfiles',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
      '#attributes' => [
        'name' => 'import_file_upload',
      ],
    ];

    $form['binary_files'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Media/Binary Files'),
      '#multiple' => TRUE,
      '#upload_location' => 'private://importfiles',
      '#upload_validators' => [
        'file_validate_extensions' => [],
      ],
      '#attributes' => [
        'name' => 'import_file_upload',
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next'),
    ];

    return $form;
  }

  /**
   * Process callback for the managed file upload to add file specific import errors.
   */
  public static function processManagedFile(&$element, FormStateInterface $form_state, &$complete_form) {
    // Call the original handler.
    $element = ManagedFile::processManagedFile($element, $form_state, $complete_form);

    // Get our messages and mark up the file titles as needed.
    $tempstore = \Drupal::service('tempstore.private');
    $store = $tempstore->get('mukurtu_import');
    $messages = $store->get('batch_results_messages') ?? [];
    foreach ($messages as $message) {
      if (isset($message['fid']) && isset($element["file_{$message['fid']}"])) {
        $errorComponents = explode(':', $message['message'], 2);
        $errorMessage = $errorComponents[1] ?? $errorComponents[0];
        $element["file_{$message['fid']}"]["selected"]["#title"] .= "<span class=\"import-error\">{$errorMessage}</span>";
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $metadataFiles = $form_state->getValue('metadata_files');

    foreach ($metadataFiles as $fid) {
      /** @var \Drupal\file\FileInterface $file */
      $file = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->load($fid);

      if (!$file) {
        $form_state->setError($form['metadata_files'], $this->t("There was an error reading the uploaded file. Remove the file and try uploading again."));
        return;
      }

      // For CSV files, check if we can read the headers.
      if ($file->getMimeType() == 'text/csv') {
        try {
          $headers = $this->getCSVHeaders($file);
        } catch (Exception $e) {
          $form_state->setError($form['metadata_files'], $this->t("Could not parse CSV for file %file.", ['%file' => $file->getFilename()]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $metadataFiles = $form_state->getValue('metadata_files');
    $this->setMetadataFiles($metadataFiles);

    $fid = $form_state->getValue('metadata_files')[0] ?? NULL;
    if (!$fid) {
      return;
    }

    /** @var \Drupal\file\FileInterface|null $file*/
    $file = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->load($fid);

    if (!$file) {
      return;
    }

    $form_state->setRedirect('mukurtu_import.import_files');
  }

}
