<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;

/**
 * Tests that separate import runs of the same file don't share state.
 *
 * Two runs that shared a migration ID shared one status, ID map, and message
 * table, so the first to finish dropped the other's tables mid-import.
 */
class ImportRunIsolationTest extends MukurtuImportTestBase {

  /**
   * Each submission of the execute step gets its own migration IDs.
   */
  public function testEachSubmissionGetsItsOwnMigrationIds() {
    require_once $this->root . '/core/includes/form.inc';

    $file = $this->createCsvFile([['title'], ['A title']]);
    $strategy = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $strategy->setTargetEntityTypeId('node');
    $strategy->setTargetBundle('protocol_aware_content');
    $strategy->setMapping([['source' => 'title', 'target' => 'title']]);

    $ids = [];
    foreach ([1, 2] as $run) {
      $batch = &batch_get();
      $batch = [];

      $form_object = TestExecuteImportForm::create($this->container);
      $form_object->testMetadataFids = [(int) $file->id()];
      $form_object->setImportConfig($file->id(), $strategy);
      $form_object->setMetadataFileWeights([$file->id() => 0]);
      $form = [];
      $form_object->submitForm($form, new FormState());

      $definition = batch_get()['sets'][0]['operations'][0][1][0];
      $ids[$run] = $definition['id'];
    }

    $this->assertNotSame($ids[1], $ids[2]);
    $suffix = "__{$this->currentUser->id()}__{$file->id()}__node__protocol_aware_content";
    foreach ($ids as $id) {
      $this->assertStringEndsWith($suffix, $id);
    }
  }

  /**
   * Cross-file lookups find an upstream map table whose name was truncated.
   *
   * Core truncates ID map table names to 63 characters. The per-run token
   * makes IDs long enough to hit that with a long bundle name, and the
   * lookup used to build the untruncated name and silently find nothing.
   */
  public function testLookupFindsTruncatedUpstreamMapTable() {
    $long_id = 'a1b2c3d4__1__1234__taxonomy_term__' . str_repeat('b', 32);
    $this->assertGreaterThan(63, strlen('migrate_map_' . $long_id));

    $migration = $this->container->get('plugin.manager.migration')->createStubMigration([
      'id' => $long_id,
      'source' => [
        'plugin' => 'embedded_data',
        'data_rows' => [],
        'ids' => ['name' => ['type' => 'string']],
      ],
      'process' => [],
      'destination' => ['plugin' => 'entity:node'],
    ]);
    $source_ids = ['name' => ['type' => 'string']];
    $migration->getIdMap()->saveIdMapping(new Row(['name' => 'Upstream Label'], $source_ids), ['42']);

    $lookup = $this->container->get('plugin.manager.migrate.process')
      ->createInstance('import_migration_lookup', ['migration_ids' => [$long_id]]);
    $result = $lookup->transform(
      'Upstream Label',
      $this->createMock(MigrateExecutableInterface::class),
      new Row([], []),
      'field_reference',
    );

    $this->assertEquals('42', $result);
  }

}
