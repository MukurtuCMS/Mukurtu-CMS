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
    $form['table'] = [
      '#type' => 'table',
      '#caption' => $this->t('Review your import. Once you begin the import you cannot stop it. There is no way to rollback the import. Click the "Start Import" button to begin.'),
      '#header' => [
        $this->t('Filename'),
        $this->t('Import Configuration'),
        $this->t('Destination Import Type'),
      ],
      '#attributes' => [
        'id' => 'import-review',
      ],
    ];

    foreach ($this->getMetadataFiles() as $fid) {
      $filename = $this->getImportFilename($fid);
      $import_config_for_file = $this->getImportConfig((int) $fid);

      // Filename.
      $form['table'][$fid]['filename'] = [
        '#type' => 'markup',
        '#markup' => "<div>$filename</div>",
      ];

      // Import Configuration.
      $form['table'][$fid]['config'] = [
        '#type' => 'markup',
        '#markup' => "<div>{$import_config_for_file->label()}</div>",
      ];

      // Destination Type.
      $entity_label = $this->entityTypeManager->getDefinition($import_config_for_file->getTargetEntityTypeId())->getLabel();
      $bundle_info = $this->entityBundleInfo->getBundleInfo($import_config_for_file->getTargetEntityTypeId());
      $bundle_label = $bundle_info[$import_config_for_file->getTargetBundle()]['label'] ?? "";
      $form['table'][$fid]['destination'] = [
        '#type' => 'markup',
        '#markup' => "<div>$entity_label: $bundle_label</div>",
      ];

    }

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

    return $form;
  }

  public function submitBack(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('mukurtu_import.import_files');
  }

  public function startImport(array &$form, FormStateInterface $form_state) {
    // $metadataFiles sorted by weight in this case.
    $metadataFiles = array_keys($this->getMetadataFileWeights());
    $migrationDefinitions = [];

    // Build migrations for each input file.
    foreach ($metadataFiles as $fid) {
      $config = $this->getImportConfig($fid);
      $metadataFile = $this->entityTypeManager->getStorage('file')->load($fid);
      if (!$metadataFile) {
        continue;
      }

      $migrationDefinitions[] = $config->toDefinition($metadataFile) + ['mukurtu_import_message' => $this->getImportRevisionMessage()];
    }

    // Run the migrations.
    $migrateMessage = new MigrateMessage();
    $bootstrapMigration = \Drupal::service('plugin.manager.migration')->createStubMigration(reset($migrationDefinitions));
    $executable = new ImportBatchExecutable($bootstrapMigration, $migrateMessage);

    try {
      $executable->batchImportMultiple($migrationDefinitions);
    } catch (Exception $e) {
      // @todo remove this after testing.
      dpm($e);
    }

    $form_state->setRedirect('mukurtu_import.import_results');
  }

}
