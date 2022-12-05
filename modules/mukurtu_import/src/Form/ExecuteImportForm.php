<?php

namespace Drupal\mukurtu_import\Form;

use Drupal\mukurtu_import\Form\ImportBaseForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\migrate_tools\MigrateBatchExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
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
      //dpm($importDefinition);
      $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($importDefinition);
      $migrateMessage = new MigrateMessage();
      $executable = new MigrateBatchExecutable($migration, $migrateMessage);
      //$executable = new MigrateExecutable($migration);

      try {
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
    return $form;
  }

}
