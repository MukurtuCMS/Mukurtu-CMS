<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Provides tests for the "index_directly" functionality.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class DirectIndexingTest extends KernelTestBase {

  use PostRequestIndexingTrait;

  /**
   * The search server used for testing.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $server;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_test',
    'user',
    'system',
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('search_api_task');
    $this->installConfig('search_api');

    // Create a test server.
    $this->server = Server::create([
      'name' => 'Test server',
      'id' => 'test',
      'status' => 1,
      'backend' => 'search_api_test',
    ]);
    $this->server->save();
  }

  /**
   * Tests index_directly works and is overridden by start/stopBatchTracking().
   */
  public function testDirectIndexing(): void {
    // Create a test entity for indexing.
    $entity = EntityTest::create([
      'name' => 'Test entity',
      'type' => 'entity_test',
    ]);
    $entity->save();
    // Create a second test entity that never gets updated and should never get
    // directly indexed.
    EntityTest::create([
      'name' => 'Test entity 2',
      'type' => 'entity_test',
    ])->save();

    // Create two indexes to ensure batch tracking is isolated.
    $index_1 = $this->createIndex();
    $index_1->save();
    $tracker_1 = $index_1->getTrackerInstance();
    $index_2 = $this->createIndex();
    $index_2->save();
    $tracker_2 = $index_2->getTrackerInstance();

    // At first nothing is indexed.
    $this->assertEquals(2, $tracker_1->getTotalItemsCount());
    $this->assertEquals(0, $tracker_1->getIndexedItemsCount());
    $this->assertEquals(2, $tracker_2->getTotalItemsCount());
    $this->assertEquals(0, $tracker_2->getIndexedItemsCount());

    // Start batch tracking mode for index 1 only.
    $index_1->startBatchTracking();
    $entity->save();
    $this->triggerPostRequestIndexing();

    // Index 1 shouldn't have indexed the entity; index 2 should've indexed as
    // normal.
    $this->assertEquals(2, $tracker_1->getTotalItemsCount());
    $this->assertEquals(0, $tracker_1->getIndexedItemsCount());
    $this->assertEquals(2, $tracker_2->getTotalItemsCount());
    $this->assertEquals(1, $tracker_2->getIndexedItemsCount());

    // Start batch tracking mode a second time for index 1.
    $index_1->startBatchTracking();
    $entity->save();
    $this->triggerPostRequestIndexing();

    // Index 1 shouldn't have indexed anything.
    $this->assertEquals(2, $tracker_1->getTotalItemsCount());
    $this->assertEquals(0, $tracker_1->getIndexedItemsCount());

    // Make a call to stop batch tracking: because we've started it twice, this
    // shouldn't actually stop batch tracking.
    $index_1->stopBatchTracking();
    $entity->save();
    $this->triggerPostRequestIndexing();

    // Index 1 still shouldn't have indexed the entity because it's in batch
    // tracking mode.
    $this->assertEquals(2, $tracker_1->getTotalItemsCount());
    $this->assertEquals(0, $tracker_1->getIndexedItemsCount());

    // Make a second call to stop batch tracking: this should actually stop
    // batch tracking mode.
    $index_1->stopBatchTracking();
    $entity->save();
    $this->triggerPostRequestIndexing();

    // Index 1 should now have indexed the entity because batch tracking mode's
    // been stopped.
    $this->assertEquals(2, $tracker_1->getTotalItemsCount());
    $this->assertEquals(1, $tracker_1->getIndexedItemsCount());

    // Simulate a page request during which the entity is first marked as
    // updated and then as deleted. The entity should now have been removed from
    // both the tracker and the server.
    $this->checkUpdateDeleteRequest($index_1, $entity);

    // An exception should be thrown if you try to stop batch tracking again.
    $this->expectException(SearchApiException::class);
    $index_1->stopBatchTracking();
  }

  /**
   * Checks page requests where an item is first updated than ignored.
   *
   * This specifically targets cases where the item is not deleted, but
   * discarded from tracking in a way that lets it still be loaded.
   *
   * @param \Drupal\search_api\IndexInterface $index
   * @param \Drupal\entity_test\Entity\EntityTest $entity
   */
  protected function checkUpdateDeleteRequest(IndexInterface $index, EntityTest $entity): void {
    $state = \Drupal::state();
    $key = 'search_api_test.backend.indexed.' . $index->id();
    $datasource_id = 'entity:entity_test';
    $item_id = $entity->id() . ':en';
    $indexed_items = array_keys($state->get($key, []));
    $this->assertEquals(["$datasource_id/$item_id"], $indexed_items);

    $index->trackItemsUpdated($datasource_id, [$item_id]);
    $index->trackItemsDeleted($datasource_id, [$item_id]);
    $this->triggerPostRequestIndexing();

    $indexed_items = array_keys($state->get($key, []));
    $this->assertEquals([], $indexed_items);
  }

  /**
   * Creates a test index.
   *
   * @return \Drupal\search_api\IndexInterface
   *   A test index.
   */
  protected function createIndex(): IndexInterface {
    return Index::create([
      'name' => $this->getRandomGenerator()->string(),
      'id' => $this->getRandomGenerator()->name(),
      'status' => 1,
      'datasource_settings' => [
        'entity:entity_test' => [],
      ],
      'tracker_settings' => [
        'default' => [],
      ],
      'server' => $this->server->id(),
      'options' => ['index_directly' => TRUE],
    ]);
  }

}
