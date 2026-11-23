<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;

/**
 * Tests that import_migration_lookup rejects labels shared by several rows.
 *
 * Regression test for #2691: when a downstream CSV referenced an upstream
 * row by a title that several upstream rows shared, the lookup returned
 * whichever row the database found first and linked it without warning.
 * It now fails the row with the same message mukurtu_entity_lookup uses for
 * ambiguous labels on existing content.
 */
class ImportMigrationLookupAmbiguityTest extends MukurtuImportTestBase {

  /**
   * Imports an upstream CSV and returns its migration.
   *
   * @param array $rows
   *   CSV rows, header first.
   * @param array $lookup_columns
   *   Columns to register as lookup source IDs.
   */
  protected function importUpstream(array $rows, array $lookup_columns = ['Title']): MigrationInterface {
    $strategy = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $strategy->setTargetEntityTypeId('node');
    $strategy->setTargetBundle('protocol_aware_content');
    $strategy->setMapping([
      ['target' => 'title', 'source' => 'Title'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'Protocols'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'Sharing Setting'],
    ]);
    $definition = $strategy->toDefinition($this->createCsvFile($rows), $lookup_columns);
    $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($definition);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, (new MigrateExecutable($migration, new MigrateMessage()))->import());
    return $migration;
  }

  /**
   * Runs the lookup plugin against the given migrations.
   */
  protected function lookup(string $value, array $migrations): mixed {
    $plugin = \Drupal::service('plugin.manager.migrate.process')->createInstance('import_migration_lookup', [
      'migration_ids' => array_map(fn(MigrationInterface $m) => $m->id(), $migrations),
    ]);
    return $plugin->transform($value, new MigrateExecutable(reset($migrations), new MigrateMessage()), new Row([], []), 'field_related_content');
  }

  /**
   * Returns the node ID for a title that exists exactly once.
   */
  protected function nodeIdByTitle(string $title): int|string {
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['title' => $title]);
    $this->assertCount(1, $nodes);
    return array_key_first($nodes);
  }

  /**
   * Upstream rows: two share a title, one is unique.
   */
  protected function upstreamRows(): array {
    $pid = $this->protocol->id();
    return [
      ['Title', 'Protocols', 'Sharing Setting'],
      ['Willow Basket', $pid, 'any'],
      ['Willow Basket', $pid, 'any'],
      ['Bolo Tie', $pid, 'any'],
    ];
  }

  /**
   * A unique title resolves; a shared title fails with the existing message.
   */
  public function testSharedTitleIsAmbiguous(): void {
    $upstream = $this->importUpstream($this->upstreamRows());

    $this->assertEquals($this->nodeIdByTitle('Bolo Tie'), $this->lookup('Bolo Tie', [$upstream]));
    $this->assertSame('No Such Item', $this->lookup('No Such Item', [$upstream]), 'An unmatched value passes through for downstream plugins.');

    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage('"Willow Basket" is ambiguous, multiple items share the same label. Try referencing the item\'s ID.');
    $this->lookup('Willow Basket', [$upstream]);
  }

  /**
   * One row matched through two source ID columns is not ambiguous.
   */
  public function testSameRowInTwoColumnsIsNotAmbiguous(): void {
    $pid = $this->protocol->id();
    $upstream = $this->importUpstream([
      ['Title', 'Nickname', 'Protocols', 'Sharing Setting'],
      ['Bolo Tie', 'Bolo Tie', $pid, 'any'],
    ], ['Title', 'Nickname']);

    $this->assertEquals($this->nodeIdByTitle('Bolo Tie'), $this->lookup('Bolo Tie', [$upstream]));
  }

  /**
   * A title created once in each of two upstream files is ambiguous.
   */
  public function testTitleInTwoMigrationsIsAmbiguous(): void {
    $pid = $this->protocol->id();
    $first = $this->importUpstream([
      ['Title', 'Protocols', 'Sharing Setting'],
      ['Beadwork', $pid, 'any'],
    ]);
    $second = $this->importUpstream([
      ['Title', 'Protocols', 'Sharing Setting'],
      ['Beadwork', $pid, 'any'],
    ]);

    $this->expectException(MigrateException::class);
    $this->lookup('Beadwork', [$first, $second]);
  }

  /**
   * In a real run, only the row with the ambiguous reference fails.
   */
  public function testAmbiguousReferenceFailsOnlyItsRow(): void {
    $upstream = $this->importUpstream($this->upstreamRows());
    $pid = $this->protocol->id();

    $strategy = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $strategy->setTargetEntityTypeId('node');
    $strategy->setTargetBundle('protocol_aware_content');
    $strategy->setMapping([
      ['target' => 'title', 'source' => 'Title'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'Protocols'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'Sharing Setting'],
    ]);
    $definition = $strategy->toDefinition($this->createCsvFile([
      ['Title', 'Ref', 'Protocols', 'Sharing Setting'],
      ['Links Ambiguous', 'Willow Basket', $pid, 'any'],
      ['Links Unique', 'Bolo Tie', $pid, 'any'],
    ]));
    // Run the lookup in a plain string property's pipeline so the test
    // doesn't depend on a particular entity reference field existing. The
    // resolved value itself is covered by testSharedTitleIsAmbiguous().
    $definition['process']['revision_log'] = [
      ['plugin' => 'get', 'source' => 'Ref'],
      ['plugin' => 'import_migration_lookup', 'migration_ids' => [$upstream->id()]],
    ];
    $downstream = \Drupal::service('plugin.manager.migration')->createStubMigration($definition);
    (new MigrateExecutable($downstream, new MigrateMessage()))->import();

    $node_storage = $this->entityTypeManager->getStorage('node');
    $this->assertEmpty($node_storage->loadByProperties(['title' => 'Links Ambiguous']), 'A row whose reference is ambiguous must not be imported.');
    $this->assertCount(1, $node_storage->loadByProperties(['title' => 'Links Unique']), 'A row whose reference is unique must still import.');

    $this->assertEquals(1, $downstream->getIdMap()->errorCount());
    $messages = iterator_to_array($downstream->getIdMap()->getMessages());
    $this->assertCount(1, $messages);
    $this->assertStringContainsString('"Willow Basket" is ambiguous', reset($messages)->message);
  }

}
