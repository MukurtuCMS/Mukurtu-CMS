<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

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
    $this->assertNotEmpty($context['results']['messages']);
  }

}
