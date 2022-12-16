<?php

namespace Drupal\mukurtu_import\Form;

use Drupal\mukurtu_import\Form\ImportBaseForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_import\ImportBatchExecutable;
use Drupal\migrate\MigrateMessage;
use Exception;

class ExecuteImportForm extends ImportBaseForm {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_import_execute_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['debug_message'] = ['#markup' => "<div>This is only a temporary debug form.</div>"];
    $form['import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Import'),
      '#button_type' => 'primary',
      '#submit' => ['::startImport'],
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#button_type' => 'primary',
      '#submit' => ['::submitBack'],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start a new import'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->reset();
    $form_state->setRedirect('mukurtu_import.file_upload');
  }

  public function submitBack(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('mukurtu_import.import_files');
  }

  public function startImport(array &$form, FormStateInterface $form_state) {
    $metadataFiles = $this->getMetadataFiles();
    $migrationDefinitions = [];

    // Build migrations for each input file.
    foreach ($metadataFiles as $fid) {
      $config = $this->getImportConfig($fid);
      $metadataFile = $this->entityTypeManager->getStorage('file')->load($fid);
      if (!$metadataFile) {
        continue;
      }

      $migrationDefinitions[] = $config->toDefinition($metadataFile);
    }

    // Run the migrations.
    $migrateMessage = new MigrateMessage();
    $bootstrapMigration = \Drupal::service('plugin.manager.migration')->createStubMigration(reset($migrationDefinitions));
    $executable = new ImportBatchExecutable($bootstrapMigration, $migrateMessage);

    try {
      $executable->batchImportMultiple($migrationDefinitions);
    } catch (Exception $e) {
      dpm($e);
    }
  }

  /**
   * Testing our ability to get info from the core migration tables for reporting.
   */
  protected function tableTest($migration) {
    $map_table = $migration->getIdMap()->mapTableName();

    $database = \Drupal::database();
    if (!$database->schema()->tableExists($map_table)) {
      throw new \InvalidArgumentException();
    }

    $query = $database->select($map_table, 'map')
      ->fields('map', ['destid1', 'source_row_status', 'last_imported'])
      ->orderBy('last_imported', 'DESC');
    $results = $query->execute();
    foreach ($results as $row) {
      dpm($row->destid1);
    }
  }

}
