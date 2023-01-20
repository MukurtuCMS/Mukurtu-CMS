<?php

namespace Drupal\mukurtu_import\Form;

use Drupal\mukurtu_import\Form\ImportBaseForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
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
    $form['metadata_files'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Metadata Files'),
      '#process' => [[static::class, 'processManagedFile']],
      '#multiple' => TRUE,
      '#default_value' => $this->getMetadataFiles(),
      '#upload_location' => $this->getMetadataUploadLocation(),
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
      '#process' => [[static::class, 'flagPermanentFiles']],
      '#multiple' => TRUE,
      '#default_value' => $this->getBinaryFiles(),
      '#upload_location' => $this->getBinaryUploadLocation(),
      '#upload_validators' => [
        'file_validate_extensions' => [],
      ],
      '#attributes' => [
        'name' => 'import_file_upload',
      ],
    ];

    // This is unfortunate but we need to pass this path to flagPermanentFiles.
    $form_state->set('binary_file_upload_location', $this->getBinaryUploadLocation());

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
   * Process callback to disable the removal checkbox for permanent files.
   */
  public static function flagPermanentFiles(&$element, FormStateInterface $form_state, &$complete_form) {
    // Call the original handler.
    $element = ManagedFile::processManagedFile($element, $form_state, $complete_form);

    // Get the upload folder for the binary files.
    $uploadLocation = $form_state->get('binary_file_upload_location');

    if ($uploadLocation) {
      $query = \Drupal::entityTypeManager()->getStorage('file')->getQuery();
      $permanentFiles = $query->condition('uri', $uploadLocation, 'STARTS_WITH')
        ->condition('status', FileInterface::STATUS_PERMANENT)
        ->accessCheck(TRUE)
        ->execute();

      // Disable the remove checkbox for all the permanent files.
      foreach ($permanentFiles as $pf) {
        if (isset($element["file_$pf"])) {
          $element["file_$pf"]['selected']['#disabled'] = TRUE;
          $element["file_$pf"]['selected']['#attributes'] = ['title' => t("This file is in use and cannot be removed.")];
        }
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $metadataFiles = $form_state->getValue('metadata_files');

    if (empty($metadataFiles)) {
      $form_state->setError($form['metadata_files'], $this->t("You must upload at least one file to import."));
    }

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
    $form_state->setRedirect('mukurtu_import.import_files');
  }

}
