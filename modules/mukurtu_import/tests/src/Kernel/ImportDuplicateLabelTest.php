<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;

/**
 * Tests that rows sharing a label are not collapsed into one entity.
 *
 * Regression test: when a CSV maps an entity reference field whose target
 * type is created by a migration in the same import (including the CSV
 * itself, for example a Digital Heritage sheet with a "Related Content"
 * column), ExecuteImportForm passes that migration's label column (Title)
 * to toDefinition() as a lookup source ID. toDefinition() used the label
 * column alone as the source ID, so every row that repeated an earlier
 * row's title shared its migrate ID map key and updated that row's node
 * instead of creating its own. A 2,075-row sheet with 188 distinct titles
 * imported as "188 created, 1887 updated".
 */
class ImportDuplicateLabelTest extends MukurtuImportTestBase {

  /**
   * Builds the CSV rows: three share a title, one is unique.
   */
  protected function duplicateTitleRows(): array {
    return [
      ['Title', 'Protocols', 'Sharing Setting'],
      ['Willow Basket', $this->protocol->id(), 'any'],
      ['Willow Basket', $this->protocol->id(), 'any'],
      ['Willow Basket', $this->protocol->id(), 'any'],
      ['Bolo Tie', $this->protocol->id(), 'any'],
    ];
  }

  /**
   * Builds a strategy that maps title and protocols.
   */
  protected function strategy(): MukurtuImportStrategy {
    $strategy = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $strategy->setTargetEntityTypeId('node');
    $strategy->setTargetBundle('protocol_aware_content');
    $strategy->setMapping([
      ['target' => 'title', 'source' => 'Title'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'Protocols'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'Sharing Setting'],
    ]);
    return $strategy;
  }

  /**
   * Lookup source IDs get the _record_number tie-breaker appended.
   */
  public function testLookupSourceIdsIncludeRecordNumber(): void {
    $file = $this->createCsvFile($this->duplicateTitleRows());
    $definition = $this->strategy()->toDefinition($file, ['Title']);
    $this->assertSame(['Title', '_record_number'], $definition['source']['ids']);
  }

  /**
   * Every row creates its own node, and a label lookup still resolves.
   */
  public function testDuplicateTitlesCreateSeparateNodes(): void {
    $file = $this->createCsvFile($this->duplicateTitleRows());
    $definition = $this->strategy()->toDefinition($file, ['Title']);

    $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($definition);
    $result = (new MigrateExecutable($migration, new MigrateMessage()))->import();
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $node_storage = $this->entityTypeManager->getStorage('node');
    $this->assertCount(3, $node_storage->loadByProperties(['title' => 'Willow Basket']), 'Each row that repeats a title must create its own node.');
    $this->assertCount(1, $node_storage->loadByProperties(['title' => 'Bolo Tie']));
    $this->assertEquals(4, $migration->getIdMap()->processedCount());

    // A downstream CSV referencing this migration by title must still
    // resolve through import_migration_lookup with the extra ID column.
    $lookup = \Drupal::service('plugin.manager.migrate.process')->createInstance('import_migration_lookup', [
      'migration_ids' => [$migration->id()],
    ]);
    $row = new Row([], []);
    $bolo_tie = $node_storage->loadByProperties(['title' => 'Bolo Tie']);
    $resolved = $lookup->transform('Bolo Tie', new MigrateExecutable($migration, new MigrateMessage()), $row, 'field_related_content');
    $this->assertEquals(array_key_first($bolo_tie), $resolved);
  }

}
