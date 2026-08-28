<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\migrate_tools\MigrateBatchExecutable;
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
    $message = new ImportBatchMessage();

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
        '@migration_failed' => FALSE,
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

    // Do the import. import() only catches MigrateException and
    // MigrateSkipRowException around row processing; any other \Throwable
    // (e.g. a TypeError from a process plugin given an unexpected NULL
    // source value) escapes uncaught and also skips the status reset
    // import() normally performs on every exit path, leaving the migration
    // stuck "importing" indefinitely. This method is invoked directly as
    // Drupal core's batch operation callback, and core's own
    // _batch_process() has no try/catch around that callable, so an
    // uncaught \Throwable here crashes the whole AJAX batch request with a
    // raw HTTP 500 instead of degrading gracefully like every other
    // row-level error does. Guard against that.
    $import_crashed = FALSE;
    try {
      $result = $executable->import();
    }
    catch (\Throwable $e) {
      // import() never reached its normal completion path, so reset status
      // ourselves or this migration will refuse to run again.
      $migration->setStatus(MigrationInterface::STATUS_IDLE);
      Error::logException(\Drupal::logger('mukurtu_import'), $e);
      $result = MigrationInterface::RESULT_FAILED;
      $import_crashed = TRUE;
    }

    // Save the messages for this migration only, so errors stay attributed
    // to the file that produced them rather than being flattened across all
    // files in the batch.
    $context['results'][$migration->id()]['messages'] = array_merge(
      $context['results'][$migration->id()]['messages'],
      iterator_to_array($executable->getIdMap()->getMessages())
    );
    foreach ($message->getErrorMessages() as $error_message) {
      $context['results'][$migration->id()]['messages'][] = (object) ['message' => $error_message, 'src_ID' => NULL];
    }

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
    // RESULT_FAILED means the migration aborted outright (e.g. a source
    // plugin exception at rewind()), possibly before processing a single
    // row -- distinct from @failures, which only counts rows that were
    // actually attempted. See https://github.com/MukurtuCMS/Mukurtu-CMS/issues/154.
    $context['results'][$migration->id()]['@migration_failed'] = $context['results'][$migration->id()]['@migration_failed'] || $result === MigrationInterface::RESULT_FAILED;

    if ($import_crashed) {
      // An uncaught error aborted the import partway through; make sure
      // that's reflected as a failure even if every row processed before
      // the crash happened to succeed.
      if (empty($context['results'][$migration->id()]['@failures'])) {
        $context['results'][$migration->id()]['@failures'] = 1;
      }
      $context['results'][$migration->id()]['messages'][] = (object) [
        'message' => (string) t('An unexpected error interrupted the import of this file and it could not be completed. Check that your file\'s columns match the selected import template, or use "Customize Settings" to map the columns manually.'),
      ];
    }
    // import() can return RESULT_FAILED before a single row is attempted
    // (e.g. a source rewind() exception from a misconfigured ID column). It
    // handles that internally without throwing, so nothing above records a
    // failure count or message for it, and the batch operation itself still
    // reports success. Surface it explicitly so the results form doesn't
    // report a false success.
    elseif ($result === MigrationInterface::RESULT_FAILED && empty($context['results'][$migration->id()]['@failures'])) {
      $context['results'][$migration->id()]['@failures'] = 1;
      $context['results'][$migration->id()]['messages'][] = (object) [
        'message' => (string) t('The import for this file failed before any rows could be processed. Check that your file\'s columns match the selected import template, or use "Customize Settings" to map the columns manually.'),
      ];
    }

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

    /** @var \Drupal\mukurtu_import\MukurtuImportLogStorage $log_storage */
    $log_storage = \Drupal::service('mukurtu_import.log_storage');
    $timestamp = \Drupal::time()->getRequestTime();
    $current_uid = (int) \Drupal::currentUser()->id();
    $has_row_failures = FALSE;
    $has_migration_failure = FALSE;
    $has_silent_noop = FALSE;
    $imported_count = 0;
    $per_migration_summary = [];

    // Build the tempstore-facing flat message list and persist one log row
    // per migration (i.e. per file), keeping each file's own messages
    // correctly attributed to that file's own fid instead of merging all
    // messages in the batch under a single file. $results also carries
    // 'messages' and 'definitions' sibling keys (see
    // batchProcessImportDefinition() above) that aren't per-migration
    // results -- only entries with a '@numitems' key are.
    $messages = [];
    foreach ($results as $migration_id => $migration_result) {
      if ($migration_id === 'definitions' || !is_array($migration_result) || !isset($migration_result['@numitems'])) {
        continue;
      }

      $imported_count += ($migration_result['@created'] ?? 0) + ($migration_result['@updated'] ?? 0);
      $per_migration_summary[] = new FormattableMarkup('@name: @created created, @updated updated, @failures failed, @ignored ignored', $migration_result);

      $row_failures = isset($migration_result['@failures']) && $migration_result['@failures'] > 0;
      $migration_failed = !empty($migration_result['@migration_failed']);
      $has_row_failures = $has_row_failures || $row_failures;
      $has_migration_failure = $has_migration_failure || $migration_failed;
      if (!$row_failures && !$migration_failed && ($migration_result['@numitems'] ?? 0) > 0 && ($migration_result['@created'] ?? 0) === 0 && ($migration_result['@updated'] ?? 0) === 0) {
        // Rows were processed but nothing was actually created or updated
        // (e.g. every row was ignored), even though migrate reported no
        // per-row failures. See https://github.com/MukurtuCMS/Mukurtu-CMS/issues/154.
        $has_silent_noop = TRUE;
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
        // Source data can contain malformed UTF-8; substitute rather than
        // let json_encode() fail outright and lose the whole row's detail.
        'details' => json_encode($details, JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]',
        'timestamp' => $timestamp,
      ]);
    }

    $store->set('batch_results_success', $success && !$has_row_failures && !$has_migration_failure && !$has_silent_noop);
    // A single migration in the batch being a silent no-op shouldn't imply
    // the whole batch imported nothing -- other migrations in the same
    // batch (e.g. a separate file/entity type) may have created or updated
    // content. Only warn when nothing was created or updated anywhere in
    // the batch.
    $store->set('batch_results_noop', $has_silent_noop && !$has_row_failures && !$has_migration_failure && $imported_count === 0);
    $store->set('batch_results_messages', $messages);

    // Summarize created/updated/failed counts per target entity type. Unlike
    // node/media/community/protocol/taxonomy_term, User entities have no
    // revision log to filter a results View by, so the results form falls
    // back to this simple count summary for 'user' migrations.
    $summary = [];
    foreach ($results as $migration_id => $data) {
      if (!is_array($data) || !isset($data['@created'])) {
        continue;
      }
      // Migration IDs are formatted as "{uid}__{fid}__{entity_type}__{bundle}".
      $parts = explode('__', (string) $migration_id);
      $entity_type_id = $parts[2] ?? NULL;
      if (!$entity_type_id) {
        continue;
      }
      $summary[$entity_type_id]['created'] = ($summary[$entity_type_id]['created'] ?? 0) + $data['@created'];
      $summary[$entity_type_id]['updated'] = ($summary[$entity_type_id]['updated'] ?? 0) + $data['@updated'];
      $summary[$entity_type_id]['failures'] = ($summary[$entity_type_id]['failures'] ?? 0) + $data['@failures'];
    }
    $store->set('batch_results_summary', $summary);

    if (\Drupal::moduleHandler()->moduleExists('mukurtu_notifications')) {
      mukurtu_notifications_notify_batch_import_report($imported_count, static::buildResultsSummary($per_migration_summary, $messages));
    }

    // Clean up ID map tables for all migrations in this batch.
    // These are no longer needed after the import is complete.
    $migration_plugin_manager = \Drupal::service('plugin.manager.migration');
    foreach ($results['definitions'] ?? [] as $definition) {
      $migration = $migration_plugin_manager->createStubMigration($definition);
      $migration->getIdMap()->destroy();
    }
  }

  /**
   * Builds the HTML summary stored on the batch import report notification.
   *
   * @param \Drupal\Component\Render\FormattableMarkup[] $per_migration_summary
   *   One formatted line per migration in the batch.
   * @param array $messages
   *   Error messages collected during the batch, each with a 'message' key.
   *
   * @return string
   *   Rendered HTML for the mukurtu_batch_import_report message's
   *   field_import_results field.
   */
  protected static function buildResultsSummary(array $per_migration_summary, array $messages): string {
    $build = [
      '#theme' => 'item_list',
      '#items' => $per_migration_summary,
      '#empty' => t('No migrations ran as part of this batch.'),
    ];
    $summary = (string) \Drupal::service('renderer')->renderInIsolation($build);

    if (empty($messages)) {
      return $summary;
    }

    $error_build = [
      '#theme' => 'item_list',
      '#title' => t('Errors'),
      '#items' => array_map(static fn (array $message) => $message['message'], $messages),
    ];
    return $summary . (string) \Drupal::service('renderer')->renderInIsolation($error_build);
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
