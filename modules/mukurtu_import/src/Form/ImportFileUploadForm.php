<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Form;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\file\Element\ManagedFile;
use Drupal\mukurtu_core\SequentialUpload;
use Drupal\Core\Link;
use Drupal\mukurtu_import\SpreadsheetConversionException;
use Drupal\mukurtu_import\SpreadsheetConverter;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Mukurtu Import form.
 */
class ImportFileUploadForm extends ImportBaseForm implements TrustedCallbackInterface {

  /**
   * Converts uploaded workbooks to CSV.
   */
  protected SpreadsheetConverter $spreadsheetConverter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->spreadsheetConverter = $container->get('mukurtu_import.spreadsheet_converter');
    return $instance;
  }

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

    // Upload each file in its own request, so the server's upload limit
    // applies to each file rather than to all of them together.
    $form['metadata_files'] = SequentialUpload::apply([
      '#type' => 'managed_file',
      '#title' => $this->t('Metadata Files'),
      '#description' => $this->t('Add your metadata files (CSV, XLSX, or ODS) here. Each sheet in a workbook is imported as a separate file. You will configure how they are imported in the next step.'),
      '#process' => [[static::class, 'processManagedFile']],
      '#multiple' => TRUE,
      '#default_value' => $this->getMetadataFiles(),
      '#upload_location' => $this->getMetadataUploadLocation(),
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'csv ' . implode(' ', SpreadsheetConverter::EXTENSIONS)],
      ],
      '#attributes' => [
        'name' => 'import_file_upload',
      ],
    ]);

    $form['binary_files'] = SequentialUpload::apply([
      '#type' => 'managed_file',
      '#title' => $this->t('Media/Binary Files'),
      '#description' => $this->t('Add your media & binary files here. You can reference these files by file name in your metadata files.') . ' ' . $this->t('Maximum size per file: @size.', [
        '@size' => ByteSizeMarkup::create(Environment::getUploadMaxSize()),
      ]),
      '#multiple' => TRUE,
      '#default_value' => $this->getBinaryFiles(),
      '#upload_location' => $this->getBinaryUploadLocation(),
      '#upload_validators' => $this->getAllowedMediaExtensionsValidator(),
      '#attributes' => [
        'name' => 'import_file_upload',
      ],
    ]);

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

      // Workbooks are converted to CSV on submit. Read them through once now
      // so a bad file is reported before anything is written.
      if ($this->spreadsheetConverter->isSpreadsheet($file)) {
        try {
          $this->spreadsheetConverter->validate($file);
        }
        catch (SpreadsheetConversionException $e) {
          $form_state->setError($form['metadata_files'], $e->getUserMessage());
        }
      }
      // For CSV files, check if we can read the headers.
      elseif ($file->getMimeType() == 'text/csv') {
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
    $metadataFiles = $this->convertSpreadsheets($form_state->getValue('metadata_files') ?? []);
    if ($metadataFiles === NULL) {
      return;
    }

    // Add a default weight for any new metadata files.
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
   * Replaces each uploaded workbook with one CSV file per sheet.
   *
   * @param array $fids
   *   The uploaded metadata file IDs.
   *
   * All or nothing: if any workbook fails, the CSV files already made from
   * the others are deleted and every workbook stays as uploaded.
   *
   * @return array|null
   *   The metadata file IDs with workbooks swapped for their CSV files, or
   *   NULL if a workbook could not be converted.
   */
  protected function convertSpreadsheets(array $fids): ?array {
    $storage = $this->entityTypeManager->getStorage('file');
    $result = [];
    $workbooks = [];
    $created = [];
    foreach ($fids as $fid) {
      /** @var \Drupal\file\FileInterface|null $file */
      $file = $storage->load($fid);
      if (!$file || !$this->spreadsheetConverter->isSpreadsheet($file)) {
        $result[] = $fid;
        continue;
      }
      try {
        $csvFiles = $this->spreadsheetConverter->convert($file, $this->getMetadataUploadLocation());
      }
      catch (SpreadsheetConversionException $e) {
        foreach ($created as $csvFile) {
          $csvFile->delete();
        }
        $this->messenger()->addError($e->getUserMessage());
        return NULL;
      }
      foreach ($csvFiles as $csvFile) {
        $created[] = $csvFile;
        $result[] = $csvFile->id();
      }
      $workbooks[] = $file;
    }
    // The workbooks would otherwise be listed as metadata files too.
    foreach ($workbooks as $workbook) {
      $workbook->delete();
    }
    return $result;
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

