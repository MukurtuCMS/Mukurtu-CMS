<?php

namespace Drupal\mukurtu_import\Form;

use Drupal\mukurtu_import\Form\ImportBaseForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\migrate_tools\MigrateBatchExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrationInterface;
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
    $metadataFiles = $this->getMetadataFiles();

    $form['debug_message'] = ['#markup' => "<div>This is only a temporary debug screen.</div>"];
    foreach ($metadataFiles as $fid) {
      $config = $this->getImportConfig($fid);
      $metadataFile = $this->entityTypeManager->getStorage('file')->load($fid);
      if (!$metadataFile) {
        continue;
      }

      $importDefinition = $config->toDefinition($metadataFile);
      $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($importDefinition);
      $migration->setTrackLastImported(TRUE);

      $migrateMessage = new MigrateMessage();
      $executable = new MigrateBatchExecutable($migration, $migrateMessage);
      //$executable = new MigrateExecutable($migration);

      try {
        //$result = $executable->batchImport();
        $result = $executable->import();
        if ($result === MigrationInterface::RESULT_COMPLETED) {
          $id_map = $migration->getIdMap();
          $imported = $id_map->importedCount();
          $form[$fid] = ['#markup' => "<div>{$metadataFile->getFilename()}:$imported imported</div>"];

        } else {
          $form[$fid] = ['#markup' => "<div>{$metadataFile->getFilename()}: Something bad happened ($result)</div>"];
        }
      } catch (Exception $e) {
        dpm($e);
      }
    }

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
