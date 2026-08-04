<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import;

use Drupal\migrate_tools\MigrateBatchExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Defines an import executable class for batch import via migrate API.
 */
class ImportBatchExecutable extends MigrateBatchExecutable {

  /**
   * Batch import from a set of migration definitions rather than plugins.
   */
  public function batchImportMultiple(array $migration_definitons) {
    $operations = $this->batchFromDefinitionOperations($migration_definitons, 'import', [
      'limit' => $this->itemLimit,
      'update' => $this->updateExistingRows,
      'force' => $this->checkDependencies,
      'sync' => $this->syncSource,
      'configuration' => $this->configuration,
    ]);

    if (count($operations) > 0) {
      $batch = [
        'operations' => $operations,
        'title' => $this->t('Importing %migrate', ['%migrate' => $this->migration->label()]),
        'init_message' => $this->t('Start importing %migrate', ['%migrate' => $this->migration->label()]),
        'progress_message' => $this->t('Importing %migrate', ['%migrate' => $this->migration->label()]),
        'error_message' => $this->t('An error occurred while importing %migrate.', ['%migrate' => $this->migration->label()]),
        'finished' => '\Drupal\mukurtu_import\ImportBatchExecutable::batchFinishedImport',
      ];

      batch_set($batch);
    }
  }

  /**
   * Build the batch operations array for migration definitions.
   */
  protected function batchFromDefinitionOperations(array $migration_definitons, string $operation, array $options = []): array {
    $operations = [];
    foreach ($migration_definitons as $migration_definition) {
      $operations[] = [
        sprintf('%s::%s', self::class, 'batchProcessImportDefinition'),
        [$migration_definition, $options],
      ];
    }
    return $operations;
  }

  /**
   * Batch callback for batchImportMultiple.
   */
  public static function batchProcessImportDefinition($migration_definition, $options, &$context) {
    if (empty($context['sandbox'])) {
      $context['finished'] = 0;
      $context['sandbox'] = [];
      $context['sandbox']['total'] = 0;
      $context['sandbox']['counter'] = 0;
      $context['sandbox']['batch_limit'] = 0;
      $context['sandbox']['operation'] = self::BATCH_IMPORT;
    }
    $message = new MigrateMessage();

    $migration_plugin_manager = \Drupal::service('plugin.manager.migration');
    $migration = $migration_plugin_manager->createStubMigration($migration_definition);
    unset($options['configuration']);
    if (!empty($options['limit']) && isset($context['results'][$migration->id()]['@numitems'])) {
      $options['limit'] -= $context['results'][$migration->id()]['@numitems'];
    }
    $executable = new ImportBatchExecutable(
      $migration,
      $message,
      \Drupal::service('keyvalue'),
      \Drupal::time(),
      \Drupal::translation(),
      $migration_plugin_manager,
      $options,
    );
    if (empty($context['sandbox']['total'])) {
      $context['sandbox']['total'] = $executable->getSource()->count();
      $context['sandbox']['batch_limit'] = $executable->calculateBatchLimit($context);
      $context['results'][$migration->id()] = [
        '@numitems' => 0,
        '@created' => 0,
        '@updated' => 0,
        '@failures' => 0,
        '@ignored' => 0,
        '@name' => $migration->id(),
        'messages' => [],
        'row_details' => [],
        'import_id' => $migration_definition['mukurtu_import_id'] ?? NULL,
        'uid' => $migration_definition['mukurtu_import_uid'] ?? NULL,
        'fid' => $migration_definition['mukurtu_import_fid'] ?? NULL,
        'filename' => $migration_definition['mukurtu_import_filename'] ?? NULL,
        'entity_type_id' => $migration_definition['mukurtu_import_entity_type_id'] ?? NULL,
        'bundle' => $migration_definition['mukurtu_import_bundle'] ?? NULL,
      ];
    }

    // Every iteration, we reset our batch counter.
    $context['sandbox']['batch_counter'] = 0;

    // Make sure we know our batch context.
    $executable->setBatchContext($context);

    // Do the import.
    $result = $executable->import();

    // Save the messages for this migration only, so errors stay attributed
    // to the file that produced them rather than being flattened across all
    // files in the batch.
    $context['results'][$migration->id()]['messages'] = array_merge(
      $context['results'][$migration->id()]['messages'],
      iterator_to_array($executable->getIdMap()->getMessages())
    );

    // Store the definition for ID map cleanup after the batch completes.
    $context['results']['definitions'][$migration->id()] = $migration_definition;

    // Pull the true per-row created/updated status from the destination
    // plugin, keyed by whether the destination entity itself already
    // existed (Entity::isNew()), rather than migrate's own @created/
    // @updated counters, which track whether *this migration's* ID map had
    // already seen the source row before, not whether the destination
    // entity pre-existed.
    $destination = $migration->getDestinationPlugin();
    $row_results = method_exists($destination, 'getAndClearRowResults') ? $destination->getAndClearRowResults() : [];
    $context['results'][$migration->id()]['row_details'] = array_merge(
      $context['results'][$migration->id()]['row_details'],
      $row_results
    );
    $created_count = count(array_filter($row_results, fn(array $r) => $r['status'] === 'created'));
    $updated_count = count(array_filter($row_results, fn(array $r) => $r['status'] === 'updated'));

    // Accumulate the result across all our iterations without clobbering the
    // metadata already stored above.
    $context['results'][$migration->id()]['@numitems'] += $executable->getProcessedCount();
    $context['results'][$migration->id()]['@created'] += $created_count;
    $context['results'][$migration->id()]['@updated'] += $updated_count;
    $context['results'][$migration->id()]['@failures'] += $executable->getFailedCount();
    $context['results'][$migration->id()]['@ignored'] += $executable->getIgnoredCount();

    // Do some housekeeping.
    if ($result !== MigrationInterface::RESULT_INCOMPLETE) {
      $context['finished'] = 1;
    }
    else {
      $context['sandbox']['counter'] = $context['results'][$migration->id()]['@numitems'];
      if ($context['sandbox']['counter'] <= $context['sandbox']['total']) {
        $context['finished'] = ((float) $context['sandbox']['counter'] / (float) $context['sandbox']['total']);
        $context['message'] = t('Importing %migration (@percent%).', [
          '%migration' => $migration->label(),
          '@percent' => (int) ($context['finished'] * 100),
        ]);
      }
    }
  }

  /**
   * Finished callback for import batches.
   *
   * @param bool $success
   *   A boolean indicating whether the batch has completed successfully.
   * @param array $results
   *   The value set in $context['results'] by callback_batch_operation().
   * @param array $operations
   *   If $success is FALSE, contains the operations that remained unprocessed.
   */
  public static function batchFinishedImport(bool $success, array $results, array $operations): void {
    $tempstore = \Drupal::service('tempstore.private');
    $store = $tempstore->get('mukurtu_import');
    $store->set('batch_results_success', $success);

    /** @var \Drupal\mukurtu_import\MukurtuImportLogStorage $log_storage */
    $log_storage = \Drupal::service('mukurtu_import.log_storage');
    $timestamp = \Drupal::time()->getRequestTime();
    $current_uid = (int) \Drupal::currentUser()->id();

    // Build the tempstore-facing flat message list and persist one log row
    // per migration (i.e. per file), keeping each file's own messages
    // correctly attributed to that file's own fid instead of merging all
    // messages in the batch under a single file.
    $messages = [];
    foreach ($results as $migration_id => $migration_result) {
      if ($migration_id === 'definitions' || !is_array($migration_result) || !isset($migration_result['@numitems'])) {
        continue;
      }

      $fid = $migration_result['fid'] ?? NULL;
      $file_message_texts = [];
      $details = $migration_result['row_details'] ?? [];
      foreach ($migration_result['messages'] ?? [] as $raw_message) {
        $source_id = $raw_message->src_ID ?? NULL;
        $message = $source_id
          ? (string) t("Problem with ID @source_id: @message", ['@source_id' => $source_id, '@message' => $raw_message->message])
          : $raw_message->message;
        $messages[] = ['fid' => $fid, 'message' => $message];
        $file_message_texts[] = $message;
        $details[] = [
          'source_id' => $source_id,
          'status' => 'failed',
          'message' => $raw_message->message,
        ];
      }

      $log_storage->log([
        'import_id' => $migration_result['import_id'] ?? '',
        'migration_id' => (string) $migration_id,
        'uid' => $migration_result['uid'] ?? $current_uid,
        'fid' => $fid,
        'filename' => $migration_result['filename'] ?? '',
        'entity_type_id' => $migration_result['entity_type_id'] ?? '',
        'bundle' => $migration_result['bundle'] ?? NULL,
        'success' => empty($migration_result['@failures']) ? 1 : 0,
        'count_processed' => $migration_result['@numitems'] ?? 0,
        'count_created' => $migration_result['@created'] ?? 0,
        'count_updated' => $migration_result['@updated'] ?? 0,
        'count_failed' => $migration_result['@failures'] ?? 0,
        'count_ignored' => $migration_result['@ignored'] ?? 0,
        'messages' => implode("\n", $file_message_texts),
        'details' => json_encode($details),
        'timestamp' => $timestamp,
      ]);
    }
    $store->set('batch_results_messages', $messages);

    // Clean up ID map tables for all migrations in this batch.
    // These are no longer needed after the import is complete.
    $migration_plugin_manager = \Drupal::service('plugin.manager.migration');
    foreach ($results['definitions'] ?? [] as $definition) {
      $migration = $migration_plugin_manager->createStubMigration($definition);
      $migration->getIdMap()->destroy();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveMessage($message, $level = MigrationInterface::MESSAGE_ERROR) {
    // Clean up process pipeline error messages for easier reading by our
    // intended audience.
    if (preg_match(sprintf('/^%s:.*?:.*?:(.*)$/im', preg_quote($this->migration->getPluginId())), $message, $matches)) {
      $message = $matches[1];
    }
    parent::saveMessage($message, $level);
  }

}
