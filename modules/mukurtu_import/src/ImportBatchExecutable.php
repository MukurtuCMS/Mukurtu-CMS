<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import;

use Drupal\migrate_tools\MigrateBatchExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Core\Utility\Error;

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

    $migration = \Drupal::getContainer()->get('plugin.manager.migration')->createStubMigration($migration_definition);
    unset($options['configuration']);
    if (!empty($options['limit']) && isset($context['results'][$migration->id()]['@numitems'])) {
      $options['limit'] -= $context['results'][$migration->id()]['@numitems'];
    }
    $executable = new ImportBatchExecutable($migration, $message, $options);
    if (empty($context['sandbox']['total'])) {
      $context['sandbox']['total'] = $executable->getSource()->count();
      $context['sandbox']['batch_limit'] = $executable->calculateBatchLimit($context);
      $context['results']['messages'] = [];
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
    foreach ($results['messages'] as $rawMessage) {
      $parts = explode(':', $rawMessage->message);

      if (isset($parts[0])) {
        preg_match('/^\d+__(\d+)__.*/', $parts[0], $matches);
        $fid = $matches[1] ?? NULL;
        if ($fid) {
          /** @var \Drupal\file\FileInterface $file */
          $file = \Drupal::entityTypeManager()->getStorage('file')->load(intval($fid));
          if ($file) {
            $parts[0] = $file->getFilename();
          }
        }
        $message = join(':', $parts);
      } else {
        $message = $rawMessage->message;
      }
      $messages[] = $message;
    }
    $store->set('batch_results_messages', $messages);
  }

}
