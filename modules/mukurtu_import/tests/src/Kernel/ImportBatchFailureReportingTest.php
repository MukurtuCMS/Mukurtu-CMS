<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\mukurtu_import\ImportBatchExecutable;

/**
 * Tests that a migration aborting with RESULT_FAILED is honestly reported,
 * rather than silently showing as a success.
 *
 * Regression test: MigrateExecutable::import() catches a source plugin
 * exception thrown while building the very first Row (e.g. an 'ids' column
 * that doesn't exist in the file, which fails Row::__construct()'s "defined
 * as a source ID but has no value" check), logs it to the 'migrate'
 * watchdog channel, and returns MigrationInterface::RESULT_FAILED -- no PHP
 * exception escapes. ImportBatchExecutable::batchProcessImportDefinition()
 * only distinguished RESULT_INCOMPLETE from "anything else" and never
 * recorded this case in the created/updated/failures counts or messages,
 * so the Import Results page reported "All files imported successfully"
 * with 0/0/0 counts even though nothing was imported.
 */
class ImportBatchFailureReportingTest extends MukurtuImportTestBase {

  /**
   * Test that a RESULT_FAILED migration is recorded as a failure with an
   * explanatory message, not a silent success.
   */
  public function testFailedMigrationIsReportedAsFailure() {
    $strategy = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $strategy->setTargetEntityTypeId('user');
    $strategy->setTargetBundle('user');
    $strategy->setMapping([
      ['source' => 'Name', 'target' => 'name'],
    ]);

    $file = $this->createCsvFile([
      ['Name'],
      ['reportingtestuser'],
    ]);
    $this->assertNotNull($file);

    $definition = $strategy->toDefinition($file);
    // MukurtuImportStrategy::toDefinition() now validates any mapped ID
    // column against the file's real headers before trusting it (see
    // issue #2032 / #1573), so it can no longer produce a definition with
    // a bad 'ids' column itself. Force the condition directly instead: a
    // source ID column absent from the file, reproducing the exact
    // condition that makes MigrateExecutable::import()'s $source->rewind()
    // throw and return RESULT_FAILED before any row is processed. count()
    // doesn't validate 'ids' the same way, so this only breaks import(),
    // matching the bug's original failure point.
    $definition['source']['ids'] = ['NonexistentSourceIdColumn'];
    $options = ['limit' => 0, 'update' => 1, 'force' => 0, 'sync' => FALSE];
    $context = [];

    ImportBatchExecutable::batchProcessImportDefinition($definition, $options, $context);

    $migration_id = $definition['id'];
    $this->assertGreaterThan(0, $context['results'][$migration_id]['@failures']);
    $this->assertEquals(0, $context['results'][$migration_id]['@created']);
    $this->assertNotEmpty($context['results'][$migration_id]['messages']);

    // No user should have been created.
    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'reportingtestuser']);
    $this->assertEmpty($users);
  }

}
