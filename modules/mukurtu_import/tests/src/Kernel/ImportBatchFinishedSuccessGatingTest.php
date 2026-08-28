<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\mukurtu_import\ImportBatchExecutable;
use Drupal\node\Entity\Node;

/**
 * Tests the success/no-op gating logic in
 * ImportBatchExecutable::batchFinishedImport().
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/154
 */
class ImportBatchFinishedSuccessGatingTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('mukurtu_import', ['mukurtu_import_log']);
  }

  /**
   * Reads a key from the mukurtu_import private tempstore.
   */
  protected function getTempstoreValue(string $key) {
    return \Drupal::service('tempstore.private')->get('mukurtu_import')->get($key);
  }

  /**
   * A normal successful migration (rows created, no failures) is reported
   * as a success and not a no-op.
   */
  public function testNormalSuccessIsReportedAsSuccess(): void {
    $results = [
      'test_migration' => [
        '@numitems' => 2,
        '@created' => 2,
        '@updated' => 0,
        '@failures' => 0,
        '@ignored' => 0,
        '@name' => 'test_migration',
      ],
    ];

    ImportBatchExecutable::batchFinishedImport(TRUE, $results, []);

    $this->assertTrue($this->getTempstoreValue('batch_results_success'));
    $this->assertFalse((bool) $this->getTempstoreValue('batch_results_noop'));
  }

  /**
   * A migration with row-level failures is not reported as a success, and
   * is not treated as a silent no-op (the failure messages already explain
   * what happened).
   */
  public function testRowFailuresAreNotReportedAsSuccess(): void {
    $results = [
      '12345__1__node' => [
        '@numitems' => 2,
        '@created' => 1,
        '@updated' => 0,
        '@failures' => 1,
        '@ignored' => 0,
        '@name' => '12345__1__node',
      ],
    ];

    ImportBatchExecutable::batchFinishedImport(TRUE, $results, []);

    $this->assertFalse($this->getTempstoreValue('batch_results_success'));
    $this->assertFalse((bool) $this->getTempstoreValue('batch_results_noop'));
  }

  /**
   * A migration that processed rows but created/updated nothing, and
   * reported no per-row failures, is a silent no-op: not a success, and
   * flagged so the results form can say so.
   */
  public function testSilentNoopIsNotReportedAsSuccess(): void {
    $results = [
      'test_migration' => [
        '@numitems' => 3,
        '@created' => 0,
        '@updated' => 0,
        '@failures' => 0,
        '@ignored' => 3,
        '@name' => 'test_migration',
      ],
    ];

    ImportBatchExecutable::batchFinishedImport(TRUE, $results, []);

    $this->assertFalse($this->getTempstoreValue('batch_results_success'));
    $this->assertTrue($this->getTempstoreValue('batch_results_noop'));
  }

  /**
   * A batch made up of multiple migrations (e.g. one file/entity type each)
   * where one migration is a silent no-op but another genuinely created
   * content is not reported as a no-op: content WAS imported, even though
   * not every migration in the batch contributed something.
   */
  public function testSilentNoopInOneMigrationIsNotReportedWhenAnotherMigrationSucceeds(): void {
    $results = [
      'content_migration' => [
        '@numitems' => 3,
        '@created' => 0,
        '@updated' => 0,
        '@failures' => 0,
        '@ignored' => 3,
        '@name' => 'content_migration',
      ],
      'media_migration' => [
        '@numitems' => 15,
        '@created' => 15,
        '@updated' => 0,
        '@failures' => 0,
        '@ignored' => 0,
        '@name' => 'media_migration',
      ],
    ];

    ImportBatchExecutable::batchFinishedImport(TRUE, $results, []);

    $this->assertFalse((bool) $this->getTempstoreValue('batch_results_noop'));
  }

  /**
   * An end-to-end import that engineers a real row failure (an ambiguous
   * mukurtu_entity_lookup match, which throws a MigrateException) results
   * in getFailedCount() > 0 on the executable, and feeding the executable's
   * accumulated results into batchFinishedImport() yields
   * batch_results_success === FALSE.
   */
  public function testEndToEndRowFailureYieldsUnsuccessfulResult(): void {
    // Set up a self-referencing entity_reference field, as in
    // ImportEntityReferenceTest.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_ref',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_name' => 'field_ref',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Related Items',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => [
            'protocol_aware_content' => 'protocol_aware_content',
          ],
          'auto_create' => FALSE,
        ],
      ],
    ])->save();

    // Two nodes sharing the same title make a title-based lookup ambiguous,
    // which trips mukurtu_entity_lookup's "throw on ambiguous match" guard.
    foreach (range(0, 1) as $i) {
      $node = Node::create([
        'title' => 'Duplicate Title',
        'type' => 'protocol_aware_content',
        'status' => TRUE,
        'uid' => $this->currentUser->id(),
      ]);
      $node->setSharingSetting('any');
      $node->setProtocols([$this->protocol]);
      $node->save();
    }

    $data = [
      ['title', 'ref'],
      ['New Node', 'Duplicate Title'],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'title', 'source' => 'title'],
      ['target' => 'field_ref', 'source' => 'ref'],
    ];

    // Build the executable directly (rather than via importCsvFile()) so we
    // can read the per-status counters off of it afterward.
    $import_config = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $import_config->setTargetEntityTypeId('node');
    $import_config->setTargetBundle('protocol_aware_content');
    $import_config->setMapping($mapping);
    $definition = $import_config->toDefinition($import_file);

    $message = new MigrateMessage();
    $migration_plugin_manager = \Drupal::service('plugin.manager.migration');
    $migration = $migration_plugin_manager->createStubMigration($definition);
    $this->lastMigration = $migration;
    $time = \Drupal::service('datetime.time');
    $translation = \Drupal::service('string_translation');
    $executable = new ImportBatchExecutable(
      $migration,
      $message,
      $this->keyValue,
      $time,
      $translation,
      $migration_plugin_manager,
      [],
    );
    $executable->import();

    $this->assertGreaterThan(0, $executable->getFailedCount());

    $results = [
      $migration->id() => [
        '@numitems' => $executable->getProcessedCount(),
        '@created' => $executable->getCreatedCount(),
        '@updated' => $executable->getUpdatedCount(),
        '@failures' => $executable->getFailedCount(),
        '@ignored' => $executable->getIgnoredCount(),
        '@name' => $migration->id(),
      ],
    ];

    ImportBatchExecutable::batchFinishedImport(TRUE, $results, []);

    $this->assertFalse($this->getTempstoreValue('batch_results_success'));
  }

  /**
   * A migration flagged as fully failed (RESULT_FAILED, e.g. a source
   * plugin exception at rewind() before any row was processed) is not
   * reported as a success, even though every row counter is zero and no
   * per-row failure was ever recorded.
   */
  public function testMigrationFailedFlagIsNotReportedAsSuccess(): void {
    $results = [
      'test_migration' => [
        '@numitems' => 0,
        '@created' => 0,
        '@updated' => 0,
        '@failures' => 0,
        '@ignored' => 0,
        '@migration_failed' => TRUE,
        '@name' => 'test_migration',
      ],
    ];

    ImportBatchExecutable::batchFinishedImport(TRUE, $results, []);

    $this->assertFalse($this->getTempstoreValue('batch_results_success'));
    $this->assertFalse((bool) $this->getTempstoreValue('batch_results_noop'));
  }

  /**
   * An end-to-end import whose migration aborts entirely at rewind() (a
   * cross-migration lookup column doesn't actually exist in the file, so
   * the CSV source throws building the first Row) is reported as
   * unsuccessful, and the real error reaches batch_results_messages with
   * the "in /path/to/File.php line N" suffix stripped.
   *
   * This used to reproduce via a stale template's own "ID" column mapping
   * to a file with no such column, but #1573 intentionally changed that
   * case to fall back to record numbers instead of failing (see
   * ImportStrategyMismatchedColumnsTest). A lookup column passed in from
   * ExecuteImportForm::detectUpstreamDependencies() (used to correlate a
   * row with an upstream migration's real source IDs when importing
   * dependent entity types from separate files) bypasses that fallback,
   * since it's expected to already be validated by the caller rather than
   * user-editable mapping.
   */
  public function testEndToEndMigrationFailureYieldsUnsuccessfulResult(): void {
    $data = [
      ['title'],
      ['New Node'],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'title', 'source' => 'title'],
    ];

    $import_config = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $import_config->setTargetEntityTypeId('node');
    $import_config->setTargetBundle('protocol_aware_content');
    $import_config->setMapping($mapping);
    $definition = $import_config->toDefinition($import_file, ['Reference Number']);

    $context = [];
    ImportBatchExecutable::batchProcessImportDefinition($definition, [], $context);

    ImportBatchExecutable::batchFinishedImport(TRUE, $context['results'], []);

    $this->assertFalse($this->getTempstoreValue('batch_results_success'));
    $this->assertFalse((bool) $this->getTempstoreValue('batch_results_noop'));

    $messages = $this->getTempstoreValue('batch_results_messages');
    $this->assertNotEmpty($messages);
    $combined = implode(' ', array_column($messages, 'message'));
    // The message text passes through a TranslatableMarkup "@" placeholder,
    // which HTML-escapes it -- this is correct, since it's later inserted
    // as raw markup on the results page.
    $this->assertStringContainsString('is defined as a source ID but has no value.', $combined);
    $this->assertStringNotContainsString('.php line', $combined);
  }

}
