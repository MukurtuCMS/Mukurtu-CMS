<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
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
use Drupal\mukurtu_import\MukurtuImportFieldProcessPluginManager;
use Drupal\mukurtu_import\MukurtuImportStrategyInterface;
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
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID service.
   * @param \Drupal\mukurtu_import\MukurtuImportFieldProcessPluginManager $field_process_plugin_manager
   *   The field process plugin manager.
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
    UuidInterface $uuid,
    MukurtuImportFieldProcessPluginManager $field_process_plugin_manager,
    protected MigrationPluginManagerInterface $migrationPluginManager,
    protected KeyValueFactoryInterface $keyValue,
    protected TimeInterface $time,
    protected TranslationInterface $translation,
  ) {
    parent::__construct($temp_store_factory, $entity_type_manager, $entity_field_manager, $entity_bundle_info, $uuid, $field_process_plugin_manager);
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
      $container->get('uuid'),
      $container->get('plugin.manager.mukurtu_import_field_process'),
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

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Import'),
    ];
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#submit' => ['::submitBack'],
    ];

    return $form;
  }

  /**
   * Submit callback for the Back button.
   *
   * @param array $form
   *    An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *    The current state of the form.
   */
  public function submitBack(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('mukurtu_import.import_files');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $metadata_files = array_keys($this->getMetadataFileWeights());

    // Phase 1: Build all configs and load files.
    $configs_by_fid = [];
    $files_by_fid = [];
    foreach ($metadata_files as $fid) {
      $configs_by_fid[$fid] = $this->getImportConfig($fid);
      $files_by_fid[$fid] = $this->entityTypeManager->getStorage('file')->load($fid);
    }

    // Phase 2: Detect cross-migration dependencies.
    // Build an index of entity_type => [fid => config].
    $entity_type_index = [];
    foreach ($configs_by_fid as $fid => $config) {
      $entity_type = $config->getTargetEntityTypeId();
      $entity_type_index[$entity_type][$fid] = $config;
    }

    // Determine which upstream migrations need label-based source IDs.
    $upstream_lookup_columns = [];
    foreach ($configs_by_fid as $fid => $config) {
      $this->detectUpstreamDependencies(
        $config,
        $entity_type_index,
        $upstream_lookup_columns
      );
    }

    // Phase 2.5: Reorder files so upstream migrations run first.
    $metadata_files = $this->sortByDependencies($metadata_files, $configs_by_fid, $entity_type_index);

    // Phase 3: Build the final migration definitions.
    $migration_definitions = [];
    foreach ($metadata_files as $fid) {
      $config = $configs_by_fid[$fid];
      $file = $files_by_fid[$fid];
      if (!$file) {
        continue;
      }

      $lookup_columns = $upstream_lookup_columns[$fid] ?? [];
      $definition = $config->toDefinition($file, $lookup_columns)
        + ['mukurtu_import_message' => $this->getImportRevisionMessage()];

      $migration_definitions[$fid] = $definition;
    }

    // Phase 4: Inject import_migration_lookup into downstream definitions.
    $this->injectCrossMigrationLookups(
      $migration_definitions,
      $configs_by_fid,
      $entity_type_index
    );

    // Phase 5: Run the migrations.
    $migrate_message = new MigrateMessage();
    $ordered_definitions = array_values($migration_definitions);
    $bootstrap_migration = $this->migrationPluginManager
      ->createStubMigration(reset($ordered_definitions));
    $executable = new ImportBatchExecutable(
      $bootstrap_migration,
      $migrate_message,
      $this->keyValue,
      $this->time,
      $this->translation,
      $this->migrationPluginManager,
    );

    try {
      $executable->batchImportMultiple($ordered_definitions);
    }
    catch (Exception $e) {
      // @todo Handle gracefully.
    }

    $form_state->setRedirect('mukurtu_import.import_results');
  }

  /**
   * Detect upstream migrations that downstream entity references depend on.
   *
   * Scans a migration config's field mappings. For each entity reference field,
   * checks if any other migration in this import creates that entity type.
   * If so, records the upstream migration's label column so it can be used
   * as a source ID.
   *
   * @param \Drupal\mukurtu_import\MukurtuImportStrategyInterface $config
   *   The import config to scan.
   * @param array $entity_type_index
   *   Index of entity_type => [fid => config].
   * @param array &$upstream_lookup_columns
   *   Accumulator: fid => array of source column names (label, media source).
   */
  protected function detectUpstreamDependencies(
    MukurtuImportStrategyInterface $config,
    array $entity_type_index,
    array &$upstream_lookup_columns
  ): void {
    $entity_type_id = $config->getTargetEntityTypeId();
    $bundle = $config->getTargetBundle();
    $field_defs = $this->entityFieldManager
      ->getFieldDefinitions($entity_type_id, $bundle);

    foreach ($config->getMapping() as $mapping) {
      $target = explode('/', $mapping['target'], 2)[0];
      $field_def = $field_defs[$target] ?? NULL;
      if (!$field_def) {
        continue;
      }

      // Only care about entity reference fields.
      $field_type = $field_def->getType();
      if (!in_array($field_type, ['entity_reference', 'entity_reference_revisions'])) {
        continue;
      }

      $ref_type = $field_def->getSetting('target_type');
      if (!isset($entity_type_index[$ref_type])) {
        continue;
      }

      // This field references an entity type created by another migration.
      foreach ($entity_type_index[$ref_type] as $upstream_fid => $upstream_config) {
        $columns = [];
        $label_column = $upstream_config->getLabelSourceColumn();
        if ($label_column) {
          $columns[] = $label_column;
        }
        $media_source_column = $upstream_config->getMediaSourceColumn();
        if ($media_source_column && !in_array($media_source_column, $columns)) {
          $columns[] = $media_source_column;
        }
        if (!empty($columns)) {
          $existing = $upstream_lookup_columns[$upstream_fid] ?? [];
          $upstream_lookup_columns[$upstream_fid] = array_unique(
            array_merge($existing, $columns)
          );
        }
      }
    }
  }

  /**
   * Inject import_migration_lookup into downstream process pipelines.
   *
   * For each migration definition, scans entity reference process pipelines
   * to find mukurtu_entity_lookup steps. When the target entity type matches
   * an upstream migration, inserts import_migration_lookup before the
   * entity lookup step.
   *
   * @param array &$migration_definitions
   *   The migration definitions array, keyed by fid.
   * @param array $configs_by_fid
   *   Import configs keyed by fid.
   * @param array $entity_type_index
   *   Index of entity_type => [fid => config].
   */
  protected function injectCrossMigrationLookups(
    array &$migration_definitions,
    array $configs_by_fid,
    array $entity_type_index
  ): void {
    foreach ($migration_definitions as $fid => &$definition) {
      $config = $configs_by_fid[$fid];
      $entity_type_id = $config->getTargetEntityTypeId();
      $bundle = $config->getTargetBundle();
      $field_defs = $this->entityFieldManager
        ->getFieldDefinitions($entity_type_id, $bundle);

      foreach ($definition['process'] as $target_field => &$process) {
        // Normalize to array of steps.
        if (!is_array($process) || !isset($process[0])) {
          continue;
        }

        // Find the target field definition.
        $base_target = explode('/', $target_field, 2)[0];
        $field_def = $field_defs[$base_target] ?? NULL;
        if (!$field_def) {
          continue;
        }

        $field_type = $field_def->getType();
        if (!in_array($field_type, ['entity_reference', 'entity_reference_revisions'])) {
          continue;
        }

        $ref_type = $field_def->getSetting('target_type');
        if (!isset($entity_type_index[$ref_type])) {
          continue;
        }

        // Collect the migration IDs that create this entity type.
        $upstream_migration_ids = [];
        foreach ($entity_type_index[$ref_type] as $upstream_fid => $upstream_config) {
          if ($upstream_fid === $fid) {
            // Don't self-reference.
            continue;
          }
          if (isset($migration_definitions[$upstream_fid])) {
            $upstream_migration_ids[] = $migration_definitions[$upstream_fid]['id'];
          }
        }

        if (empty($upstream_migration_ids)) {
          continue;
        }

        // Find the position of mukurtu_entity_lookup or mukurtu_entity_generate
        // and insert import_migration_lookup before it.
        $lookup_step = [
          'plugin' => 'import_migration_lookup',
          'migration_ids' => $upstream_migration_ids,
        ];

        $insert_position = NULL;
        foreach ($process as $i => $step) {
          if (is_array($step) && isset($step['plugin'])
            && in_array($step['plugin'], ['mukurtu_entity_lookup', 'mukurtu_entity_generate'])) {
            $insert_position = $i;
            break;
          }
        }

        if ($insert_position !== NULL) {
          array_splice($process, $insert_position, 0, [$lookup_step]);
        }
      }
      unset($process);
    }
    unset($definition);
  }

  /**
   * Sort file IDs so upstream (referenced) migrations run before downstream.
   *
   * Uses a topological sort: for each file whose entity reference fields
   * target an entity type created by another file in this import, the
   * referenced file must come first.
   *
   * Falls back to the user's original weight-based ordering for files
   * with no dependency relationship.
   *
   * @param array $metadata_files
   *   File IDs in the user's original weight order.
   * @param array $configs_by_fid
   *   Import configs keyed by file ID.
   * @param array $entity_type_index
   *   Index of entity_type => [fid => config].
   *
   * @return array
   *   File IDs reordered so dependencies are satisfied.
   */
  protected function sortByDependencies(
    array $metadata_files,
    array $configs_by_fid,
    array $entity_type_index
  ): array {
    // Build a dependency graph: fid => [fids it depends on].
    $dependencies = array_fill_keys($metadata_files, []);

    foreach ($configs_by_fid as $fid => $config) {
      $entity_type_id = $config->getTargetEntityTypeId();
      $bundle = $config->getTargetBundle();
      $field_defs = $this->entityFieldManager
        ->getFieldDefinitions($entity_type_id, $bundle);

      foreach ($config->getMapping() as $mapping) {
        $target = explode('/', $mapping['target'], 2)[0];
        $field_def = $field_defs[$target] ?? NULL;
        if (!$field_def) {
          continue;
        }

        $field_type = $field_def->getType();
        if (!in_array($field_type, ['entity_reference', 'entity_reference_revisions'])) {
          continue;
        }

        $ref_type = $field_def->getSetting('target_type');
        if (!isset($entity_type_index[$ref_type])) {
          continue;
        }

        foreach (array_keys($entity_type_index[$ref_type]) as $upstream_fid) {
          if ($upstream_fid !== $fid) {
            $dependencies[$fid][] = $upstream_fid;
          }
        }
      }
      $dependencies[$fid] = array_unique($dependencies[$fid]);
    }

    // Topological sort (Kahn's algorithm). Preserve the user's weight order as
    // a tiebreaker.
    $position = array_flip($metadata_files);
    $sorted = [];
    $remaining = $dependencies;

    while (!empty($remaining)) {
      // Find all files with no unresolved dependencies.
      $ready = [];
      foreach ($remaining as $fid => $deps) {
        $unresolved = array_intersect($deps, array_keys($remaining));
        if (empty($unresolved)) {
          $ready[] = $fid;
        }
      }

      if (empty($ready)) {
        // Circular dependency — fall back to original order for remaining.
        $remaining_keys = array_keys($remaining);
        usort($remaining_keys, fn($a, $b) => $position[$a] <=> $position[$b]);
        $sorted = array_merge($sorted, $remaining_keys);
        break;
      }

      // Sort ready files by their original weight-based position.
      usort($ready, fn($a, $b) => $position[$a] <=> $position[$b]);

      foreach ($ready as $fid) {
        $sorted[] = $fid;
        unset($remaining[$fid]);
      }
    }

    return $sorted;
  }

}
