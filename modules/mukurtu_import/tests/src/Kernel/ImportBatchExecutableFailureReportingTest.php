<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_import\ImportBatchExecutable;

/**
 * Test that ImportBatchExecutable surfaces a real failure instead of a
 * false "success" when a migration's import() returns RESULT_FAILED before
 * any row could be processed (e.g. a source rewind() exception from a
 * misconfigured id column).
 */
class ImportBatchExecutableFailureReportingTest extends MukurtuImportTestBase {

  /**
   * A migration whose source ids reference a column absent from the file
   * fails during rewind(). That must be reflected in the batch results
   * (failure count + message) so the results form doesn't lie about it.
   */
  public function testRewindFailureIsRecordedInBatchResults() {
    $data = [
      ['title'],
      ['Some Title'],
    ];
    $import_file = $this->createCsvFile($data);

    $definition = [
      'id' => 'test_bad_ids_migration',
      'label' => 'Test bad ids migration',
      'source' => [
        'plugin' => 'csv',
        'path' => $import_file->getFileUri(),
        // This column does not exist in the file above.
        'ids' => ['ID'],
        'delimiter' => ',',
        'enclosure' => '"',
        'escape' => '\\',
        'track_changes' => TRUE,
        'create_record_number' => TRUE,
        'record_number_field' => '_record_number',
      ],
      'process' => [
        'title' => 'title',
      ],
      'destination' => [
        'plugin' => 'entity:node',
        'default_bundle' => 'protocol_aware_content',
      ],
    ];

    $context = [];
    ImportBatchExecutable::batchProcessImportDefinition($definition, [], $context);

    $this->assertGreaterThan(0, $context['results']['test_bad_ids_migration']['@failures']);
    $this->assertNotEmpty($context['results']['test_bad_ids_migration']['messages']);
  }

  /**
   * A process plugin that throws a non-MigrateException \Throwable (e.g. a
   * TypeError from a strictly-typed callback) must not crash the batch
   * operation; it must degrade to a recorded failure, and the migration's
   * status must be reset so it isn't left permanently stuck "importing".
   */
  public function testUncaughtThrowableIsRecordedInBatchResults() {
    $data = [
      ['title'],
      ['Some Title'],
    ];
    $import_file = $this->createCsvFile($data);

    $definition = [
      'id' => 'test_throwable_migration',
      'label' => 'Test throwable migration',
      'source' => [
        'plugin' => 'csv',
        'path' => $import_file->getFileUri(),
        'ids' => ['title'],
        'delimiter' => ',',
        'enclosure' => '"',
        'escape' => '\\',
        'track_changes' => TRUE,
        'create_record_number' => TRUE,
        'record_number_field' => '_record_number',
      ],
      'process' => [
        'title' => [
          ['plugin' => 'get', 'source' => 'title'],
          ['plugin' => 'callback', 'callable' => [self::class, 'throwingCallback']],
        ],
      ],
      'destination' => [
        'plugin' => 'entity:node',
        'default_bundle' => 'protocol_aware_content',
      ],
    ];

    $context = [];
    ImportBatchExecutable::batchProcessImportDefinition($definition, [], $context);

    $this->assertSame(1, $context['finished']);
    $this->assertGreaterThan(0, $context['results']['test_throwable_migration']['@failures']);
    $this->assertNotEmpty($context['results']['test_throwable_migration']['messages']);

    $migration_plugin_manager = \Drupal::service('plugin.manager.migration');
    $migration = $migration_plugin_manager->createStubMigration($definition);
    $this->assertSame(MigrationInterface::STATUS_IDLE, $migration->getStatus());
  }

  /**
   * A process callback that always throws a non-MigrateException Throwable,
   * to exercise ImportBatchExecutable's defense-in-depth catch.
   */
  public static function throwingCallback($value) {
    throw new \TypeError('Forced test throwable for defense-in-depth batch catch test.');
  }

}
