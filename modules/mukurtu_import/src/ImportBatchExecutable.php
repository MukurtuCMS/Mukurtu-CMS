<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import;

use Drupal\Core\Utility\Error;
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
      $context['results']['messages'] = $context['results']['messages'] ?? [];
      $context['results'][$migration->id()] = [
        '@numitems' => 0,
        '@created' => 0,
        '@updated' => 0,
        '@failures' => 0,
        '@ignored' => 0,
        '@name' => $migration->id(),
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

    // Save the messages.
    $context['results']['messages'] = array_merge($context['results']['messages'], iterator_to_array($executable->getIdMap()->getMessages()));

    // Store the definition for ID map cleanup after the batch completes.
    $context['results']['definitions'][$migration->id()] = $migration_definition;

    // Store the result; will need to combine the results of all our iterations.
    $context['results'][$migration->id()] = [
      '@numitems' => $context['results'][$migration->id()]['@numitems'] + $executable->getProcessedCount(),
      '@created' => $context['results'][$migration->id()]['@created'] + $executable->getCreatedCount(),
      '@updated' => $context['results'][$migration->id()]['@updated'] + $executable->getUpdatedCount(),
      '@failures' => $context['results'][$migration->id()]['@failures'] + $executable->getFailedCount(),
      '@ignored' => $context['results'][$migration->id()]['@ignored'] + $executable->getIgnoredCount(),
      '@name' => $migration->id(),
    ];

    if ($import_crashed) {
      // An uncaught error aborted the import partway through; make sure
      // that's reflected as a failure even if every row processed before
      // the crash happened to succeed.
      if (empty($context['results'][$migration->id()]['@failures'])) {
        $context['results'][$migration->id()]['@failures'] = 1;
      }
      $context['results']['messages'][] = (object) [
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
      $context['results']['messages'][] = (object) [
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
    $store->set('batch_results_success', $success);

    $messages = [];
    $exception_fid = NULL;

    // Find our failure point.
    foreach (array_keys($results) as $migration_id) {
      if ($migration_id === 'message') {
        continue;
      }

      if (isset($results[$migration_id]['@failures']) && $results[$migration_id]['@failures'] > 0) {
        preg_match('/^\d+__(\d+)__.*/', $migration_id, $matches);
        $fid = $matches[1] ?? NULL;
        if ($fid) {
          $storage = \Drupal::entityTypeManager()->getStorage('file');
          if ($storage->load(intval($fid))) {
            $exception_fid = $fid;
          }
        }
      }
    }

    // Tag the error messages with the fid so we can display it next to the
    // file later.
    $raw_messages = $results['messages'] ?? [];
    foreach ($raw_messages as $raw_message) {
      $source_id = $raw_message->src_ID ?? NULL;
      $message = $source_id ? t("Problem with ID @source_id: @message", ['@source_id' => $source_id, '@message' => $raw_message->message]) : $raw_message->message;
      $messages[] = ['fid' => $exception_fid ?? NULL, 'message' => $message];
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
