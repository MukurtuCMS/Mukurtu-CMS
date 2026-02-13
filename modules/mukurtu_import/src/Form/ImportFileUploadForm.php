<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\file\Element\ManagedFile;
use Drupal\Core\Link;
use Exception;

/**
 * Provides a Mukurtu Import form.
 */
class ImportFileUploadForm extends ImportBaseForm implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return [
      'processManagedFile',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_import_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['import_description_link'] = [
      '#markup' => Link::createFromRoute(
        $this->t('Import Format Information by Type'),
        'mukurtu_import.bundles_list',
        [],
      )->toString(),
    ];

    $form['metadata_files'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Metadata Files'),
      '#description' => $this->t('Add your metadata files (e.g., CSV) here. You will configure how they are imported in the next step.'),
      '#process' => [[static::class, 'processManagedFile']],
      '#multiple' => TRUE,
      '#default_value' => $this->getMetadataFiles(),
      '#upload_location' => $this->getMetadataUploadLocation(),
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'csv'],
      ],
      '#attributes' => [
        'name' => 'import_file_upload',
      ],
    ];

    $form['binary_files'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Media/Binary Files'),
      '#description' => $this->t('Add your media & binary files here. You can reference these files by file name in your metadata files.'),
      '#process' => [[static::class, 'flagPermanentFiles']],
      '#multiple' => TRUE,
      '#default_value' => $this->getBinaryFiles(),
      '#upload_location' => $this->getBinaryUploadLocation(),
      '#upload_validators' => $this->getAllowedMediaExtensionsValidator(),
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
    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#submit' => ['::resetForm'],
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
        $element["file_{$message['fid']}"]["selected"]["#title"] .= "<span class=\"import--error\">{$message['message']}</span>";
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
    $upload_location = $form_state->get('binary_file_upload_location');

    if ($upload_location) {
      $query = \Drupal::entityTypeManager()->getStorage('file')->getQuery();
      $permanent_files = $query->condition('uri', $upload_location, 'STARTS_WITH')
        ->condition('status', FileInterface::STATUS_PERMANENT)
        ->accessCheck(TRUE)
        ->execute();

      // Disable the remove checkbox for all the permanent files.
      foreach ($permanent_files as $pf) {
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
    // Skip validation if Reset button was clicked.
    $triggering_element = $form_state->getTriggeringElement();
    $clicked_button = isset($triggering_element['#parents']) ? end($triggering_element['#parents']) : '';
    if ($clicked_button === 'reset') {
      return;
    }

    $metadataFiles = $form_state->getValue('metadata_files');

    if (empty($metadataFiles)) {
      $form_state->setError($form['metadata_files'], $this->t("You must upload at least one file to import."));
    }

    foreach ($metadataFiles as $fid) {
      /** @var \Drupal\file\FileInterface $file */
      $file = $this->entityTypeManager
        ->getStorage('file')
        ->load($fid);

      if (!$file) {
        $form_state->setError($form['metadata_files'], $this->t("There was an error reading the uploaded file. Remove the file and try uploading again."));
        return;
      }

      // For CSV files, check if we can read the headers.
      if ($file->getMimeType() == 'text/csv') {
        try {
          $this->getCSVHeaders($file);
        }
        catch (Exception $e) {
          $form_state->setError($form['metadata_files'], $this->t("Could not parse CSV for file %file.", ['%file' => $file->getFilename()]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Add a default weight for any new metadata files.
    $metadataFiles = $form_state->getValue('metadata_files');
    $metadataFileWeights = $this->getMetadataFileWeights();
    foreach ($metadataFiles as $fid ) {
      if (!in_array($fid, $metadataFileWeights)) {
        $metadataFileWeights[$fid] = 0;
      }
    }
    $this->setMetadataFileWeights($metadataFileWeights);

    $form_state->setRedirect('mukurtu_import.import_files');
  }

  /**
   * Reset to a clean state.
   *
   * @param array $form
   *    An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *    The current state of the form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state): void {
    $this->reset();
  }

  /**
   * Get upload validators with all allowed media extensions.
   *
   * Dynamically discovers all file extensions allowed by media entity bundles
   * to ensure import supports the same file types as regular media uploads.
   *
   * @return array
   *   Upload validators array with FileExtension validator.
   */
  protected function getAllowedMediaExtensionsValidator(): array {
    $extensions = [];

    // Get all media bundles.
    $media_bundles = $this->entityTypeManager->getStorage('media_type')->loadMultiple();

    foreach ($media_bundles as $bundle_id => $bundle) {
      // Get field definitions for this media bundle.
      $field_definitions = $this->entityFieldManager->getFieldDefinitions('media', $bundle_id);

      // Extract file extensions from file and image fields.
      foreach ($field_definitions as $field_definition) {
        $field_type = $field_definition->getType();
        if (in_array($field_type, ['file', 'image'])) {
          $settings = $field_definition->getSettings();
          if (!empty($settings['file_extensions'])) {
            $field_extensions = explode(' ', $settings['file_extensions']);
            $extensions = array_merge($extensions, $field_extensions);
          }
        }
      }
    }

    // Deduplicate and sort for consistency.
    $extensions = array_unique($extensions);
    sort($extensions);

    // Return the validator array.
    return [
      'FileExtension' => [
        'extensions' => implode(' ', $extensions),
      ],
    ];
  }

}

