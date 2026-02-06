<?php

namespace Drupal\mukurtu_import\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_import\ImportBatchExecutable;
use Drupal\migrate\MigrateMessage;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ExecuteImportForm extends ImportBaseForm {

  /**
   * Construct a new ExecuteImportForm.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   Temp store factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_bundle_info
   *   Entity bundle info.
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migrationPluginManager
   *   Migration plugin manager.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValue
   *   Key value factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   Translation service.
   */
  public function __construct(
    PrivateTempStoreFactory $temp_store_factory,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    EntityTypeBundleInfoInterface $entity_bundle_info,
    protected MigrationPluginManagerInterface $migrationPluginManager,
    protected KeyValueFactoryInterface $keyValue,
    protected TimeInterface $time,
    protected TranslationInterface $translation,
  ) {
    parent::__construct($temp_store_factory, $entity_type_manager, $entity_field_manager, $entity_bundle_info);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('plugin.manager.migration'),
      $container->get('keyvalue'),
      $container->get('datetime.time'),
      $container->get('string_translation'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_import_execute_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
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
      $label = $import_config_for_file->label() ?? $this->t("Custom");
      $form['table'][$fid]['config'] = [
        '#type' => 'markup',
        '#markup' => "<div>{$label}</div>",
      ];

      // Destination Type.
      $entity_label = $this->entityTypeManager->getDefinition($import_config_for_file->getTargetEntityTypeId())->getLabel();
      $bundle_info = $this->entityBundleInfo->getBundleInfo($import_config_for_file->getTargetEntityTypeId());
      $bundle_label = $bundle_info[$import_config_for_file->getTargetBundle()]['label'] ?? t("Base Fields");
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

  public function submitBack(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('mukurtu_import.import_files');
  }

  public function startImport(array &$form, FormStateInterface $form_state): void {
    // $metadata_files sorted by weight in this case.
    $metadata_files = array_keys($this->getMetadataFileWeights());
    $migration_definitions = [];

    // Build migrations for each input file.
    foreach ($metadata_files as $fid) {
      $config = $this->getImportConfig($fid);
      /** @var \Drupal\file\FileInterface $metadata_file */
      $metadata_file = $this->entityTypeManager->getStorage('file')->load($fid);
      if (!$metadata_file) {
        continue;
      }

      $migration_definitions[] = $config->toDefinition($metadata_file) + ['mukurtu_import_message' => $this->getImportRevisionMessage()];
    }

    // Run the migrations.
    $migrate_message = new MigrateMessage();
    $bootstrap_migration = $this->migrationPluginManager->createStubMigration(reset($migration_definitions));
    $executable = new ImportBatchExecutable(
      $bootstrap_migration,
      $migrate_message,
      $this->keyValue,
      $this->time,
      $this->translation,
      $this->migrationPluginManager,
    );

    try {
      $executable->batchImportMultiple($migration_definitions);
    } catch (Exception $e) {
      // @todo remove this after testing.
      dpm($e);
    }

    $form_state->setRedirect('mukurtu_import.import_results');
  }

}
