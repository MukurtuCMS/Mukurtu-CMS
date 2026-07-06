<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import;

use Drupal\Component\Render\FormattableMarkup;
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

    // Do the import.
    $result = $executable->import();

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
    $imported_count = 0;
    $per_migration_summary = [];

    // Find our failure point. $results also carries 'messages' and
    // 'definitions' sibling keys (see batchProcessImportDefinition() above)
    // that aren't per-migration results -- only entries with an '@name' key
    // are.
    foreach (array_keys($results) as $migration_id) {
      if (!is_array($results[$migration_id]) || !isset($results[$migration_id]['@name'])) {
        continue;
      }

      $imported_count += ($results[$migration_id]['@created'] ?? 0) + ($results[$migration_id]['@updated'] ?? 0);
      $per_migration_summary[] = new FormattableMarkup('@name: @created created, @updated updated, @failures failed, @ignored ignored', $results[$migration_id]);

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

    mukurtu_notifications_notify_batch_import_report($imported_count, static::buildResultsSummary($per_migration_summary, $messages));

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
