<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\file\FileInterface;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\mukurtu_import\ImportBatchExecutable;
use Drupal\node\Entity\Node;

/**
 * Tests persistent logging of import runs (issue #1417).
 */
class ImportLogTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('mukurtu_import', ['mukurtu_import_log']);
  }

  /**
   * Create a protocol-aware node ready to be updated by an import row.
   */
  protected function createNode(string $title): Node {
    $node = Node::create([
      'title' => $title,
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    return $node;
  }

  /**
   * Build a migration definition the way ExecuteImportForm does, with the
   * mukurtu_import_* metadata keys the batch executable and log storage
   * depend on.
   */
  protected function buildDefinition(FileInterface $file, array $mapping, string $import_id, string $entity_type_id = 'node', string $bundle = 'protocol_aware_content'): array {
    $config = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $config->setTargetEntityTypeId($entity_type_id);
    $config->setTargetBundle($bundle);
    $config->setMapping($mapping);

    return $config->toDefinition($file) + [
      'mukurtu_import_id' => $import_id,
      'mukurtu_import_fid' => (int) $file->id(),
      'mukurtu_import_filename' => $file->getFilename(),
      'mukurtu_import_entity_type_id' => $entity_type_id,
      'mukurtu_import_bundle' => $bundle,
      'mukurtu_import_uid' => (int) $this->currentUser->id(),
    ];
  }

  /**
   * Drive the batch import callbacks the way Drupal's batch API would,
   * without needing a real batch run. Mirrors _batch_process()'s handling
   * of $context['sandbox'] between operations.
   */
  protected function runBatchImport(array $definitions): array {
    $context = ['results' => [], 'sandbox' => []];
    foreach ($definitions as $definition) {
      do {
        $context['finished'] = 1;
        ImportBatchExecutable::batchProcessImportDefinition($definition, [], $context);
      } while ($context['finished'] < 1);
      $context['sandbox'] = [];
    }
    ImportBatchExecutable::batchFinishedImport(TRUE, $context['results'], []);
    return $context['results'];
  }

  /**
   * Load all mukurtu_import_log rows for a given import_id, keyed by fid.
   */
  protected function loadLogRowsByFid(string $import_id): array {
    $rows = \Drupal::database()->select('mukurtu_import_log', 'l')
      ->fields('l')
      ->condition('l.import_id', $import_id)
      ->execute()
      ->fetchAll();
    $by_fid = [];
    foreach ($rows as $row) {
      $by_fid[$row->fid] = $row;
    }
    return $by_fid;
  }

  /**
   * A single file that imports cleanly produces one successful log row.
   */
  public function testSingleSuccessfulFileLogsSuccess() {
    $import_id = $this->container->get('uuid')->generate();
    $node = $this->createNode('Original Title');

    $data = [
      ['nid', 'title'],
      [$node->id(), 'Updated Title'],
    ];
    $file = $this->createCsvFile($data);
    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
    ];
    $definition = $this->buildDefinition($file, $mapping, $import_id);

    $this->runBatchImport([$definition]);

    $rows = $this->loadLogRowsByFid($import_id);
    $this->assertCount(1, $rows);
    $row = reset($rows);
    $this->assertEquals(1, $row->success);
    $this->assertEquals($file->id(), $row->fid);
    $this->assertEquals($file->getFilename(), $row->filename);
    $this->assertEquals('node', $row->entity_type_id);
    $this->assertEquals('protocol_aware_content', $row->bundle);
    $this->assertEquals(1, $row->count_processed);
    // Migrate's created/updated counters track whether this migration's own
    // ID map already had a mapping for the source row, not whether the
    // destination entity pre-existed outside of migrate. Since this is the
    // migration's first (and only) run, the row counts as "created" even
    // though it updated an entity that already existed in Drupal.
    $this->assertEquals(1, $row->count_created);
    $this->assertEquals(0, $row->count_updated);
    $this->assertEquals(0, $row->count_failed);
    $this->assertEmpty($row->messages);
  }

  /**
   * A file with a failing row (blanking out the required title) logs the
   * failure with readable error text.
   */
  public function testSingleFailingFileLogsFailure() {
    $import_id = $this->container->get('uuid')->generate();
    $node = $this->createNode('Original Title');

    // An empty title fails Node's required title validation.
    $data = [
      ['nid', 'title'],
      [$node->id(), ''],
    ];
    $file = $this->createCsvFile($data);
    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
    ];
    $definition = $this->buildDefinition($file, $mapping, $import_id);

    $this->runBatchImport([$definition]);

    $rows = $this->loadLogRowsByFid($import_id);
    $this->assertCount(1, $rows);
    $row = reset($rows);
    $this->assertEquals(0, $row->success);
    $this->assertEquals(1, $row->count_failed);
    $this->assertNotEmpty($row->messages);
  }

  /**
   * Regression test for the fid mis-attribution bug: when two files in the
   * same batch both fail, each file's log row and error messages must stay
   * attributed to that file only, not merged onto the last-failing file.
   */
  public function testMultiFileBatchAttributesFailuresPerFile() {
    $import_id = $this->container->get('uuid')->generate();
    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
    ];

    // File A: two failing rows (two nodes, both blanked out).
    $node_a1 = $this->createNode('A1');
    $node_a2 = $this->createNode('A2');
    $data_a = [
      ['nid', 'title'],
      [$node_a1->id(), ''],
      [$node_a2->id(), ''],
    ];
    $file_a = $this->createCsvFile($data_a);
    $definition_a = $this->buildDefinition($file_a, $mapping, $import_id);

    // File B: one failing row.
    $node_b1 = $this->createNode('B1');
    $data_b = [
      ['nid', 'title'],
      [$node_b1->id(), ''],
    ];
    $file_b = $this->createCsvFile($data_b);
    $definition_b = $this->buildDefinition($file_b, $mapping, $import_id);

    $this->runBatchImport([$definition_a, $definition_b]);

    $rows = $this->loadLogRowsByFid($import_id);
    $this->assertCount(2, $rows, 'Each file gets its own log row.');

    $row_a = $rows[$file_a->id()];
    $row_b = $rows[$file_b->id()];

    $this->assertEquals($file_a->getFilename(), $row_a->filename);
    $this->assertEquals(2, $row_a->count_failed, "File A's own two failures, not merged with file B's.");
    $this->assertCount(2, array_filter(explode("\n", (string) $row_a->messages)));

    $this->assertEquals($file_b->getFilename(), $row_b->filename);
    $this->assertEquals(1, $row_b->count_failed, "File B's own single failure, not inflated by file A's.");
    $this->assertCount(1, array_filter(explode("\n", (string) $row_b->messages)));
  }

  /**
   * The tempstore-facing batch_results_messages shape consumed by
   * ImportFileUploadForm/ImportResultsForm must remain a flat list of
   * ['fid' => ..., 'message' => ...], with each entry now correctly
   * attributed to its own file.
   */
  public function testTempstoreMessageShapePreservedAndCorrectlyAttributed() {
    $import_id = $this->container->get('uuid')->generate();
    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
    ];

    $node_a = $this->createNode('A');
    $data_a = [['nid', 'title'], [$node_a->id(), '']];
    $file_a = $this->createCsvFile($data_a);
    $definition_a = $this->buildDefinition($file_a, $mapping, $import_id);

    $node_b = $this->createNode('B');
    $data_b = [['nid', 'title'], [$node_b->id(), '']];
    $file_b = $this->createCsvFile($data_b);
    $definition_b = $this->buildDefinition($file_b, $mapping, $import_id);

    $this->runBatchImport([$definition_a, $definition_b]);

    $store = $this->container->get('tempstore.private')->get('mukurtu_import');
    $messages = $store->get('batch_results_messages');

    $this->assertIsArray($messages);
    $this->assertCount(2, $messages);
    $fids = array_column($messages, 'fid');
    sort($fids);
    $expected = [$file_a->id(), $file_b->id()];
    sort($expected);
    $this->assertEquals($expected, $fids);
    foreach ($messages as $message) {
      $this->assertArrayHasKey('fid', $message);
      $this->assertArrayHasKey('message', $message);
    }
  }

  /**
   * A mixed batch (one file succeeds, one fails) logs one row of each.
   */
  public function testMixedBatchLogsSuccessAndFailureSeparately() {
    $import_id = $this->container->get('uuid')->generate();
    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
    ];

    $node_success = $this->createNode('Success Node');
    $data_success = [['nid', 'title'], [$node_success->id(), 'Updated']];
    $file_success = $this->createCsvFile($data_success);
    $definition_success = $this->buildDefinition($file_success, $mapping, $import_id);

    $node_failure = $this->createNode('Failure Node');
    $data_failure = [['nid', 'title'], [$node_failure->id(), '']];
    $file_failure = $this->createCsvFile($data_failure);
    $definition_failure = $this->buildDefinition($file_failure, $mapping, $import_id);

    $this->runBatchImport([$definition_success, $definition_failure]);

    $rows = $this->loadLogRowsByFid($import_id);
    $this->assertCount(2, $rows);
    $this->assertEquals(1, $rows[$file_success->id()]->success);
    $this->assertEquals(0, $rows[$file_failure->id()]->success);
  }

}
