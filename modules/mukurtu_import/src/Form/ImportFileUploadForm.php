<?php

namespace Drupal\mukurtu_import\Form;

use Drupal\mukurtu_import\Form\ImportBaseForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\migrate\MigrateExecutable;
use Exception;

/**
 * Provides a Mukurtu Import form.
 */
class ImportFileUploadForm extends ImportBaseForm {

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
    // Show any existing error messages if this is a return from a previous
    // import run.
    if ($messages = $this->getMessages()) {
      foreach ($messages as $message) {
        $this->messenger()->addError($message['message']);
      }
    }

    $form['metadata_files'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Metadata Files'),
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

    // Set process to defaults.
    //$this->initializeProcess($file->id());


    $data_rows = [
      ['id' => '100047', 'title' => 'Wow this is a title'],
    ];
    //$ids = ['id' => ['type' => 'integer'], 'title' => ['type' => 'string']];
    $ids = ['id', 'title'];
    $definition = [
      'id' => 'mukurtu_test_import',
      'migration_tags' => ['Import and rollback test'],
      'source' => [
        'plugin' => 'csv',
        'path' => $file->getFileUri(),
        //'data_rows' => $data_rows,
        'ids' => $ids,
        'track_changes' => TRUE,
      ],
      'process' => [
        'nid' => 'id',
        'title' => 'title',
        'field_description' => 'Da Desc',
        'field_cultural_narrative' => 'field_cultural_narrative',
      ],
      'destination' => ['plugin' => 'entity:node'],
    ];

    $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($definition);
    $id_map = $migration->getIdMap();

    $executable = new MigrateExecutable($migration);
    //$map = $executable->getIdMap();
    //dpm($map);
    //$executable->import();

    //$migration = \Drupal::service('plugin.manager.migration')->createInstance('playing_around');
    //$migration->getIdMap()->prepareUpdate(); // <-- this :)
    //$executable = new \Drupal\migrate_tools\MigrateExecutable($migration, new \Drupal\migrate\MigrateMessage());
    //$executable->import();
    //$this->messenger()->addStatus($this->t('The message has been sent.'));
    $form_state->setRedirect('mukurtu_import.import_files');
  }

}
