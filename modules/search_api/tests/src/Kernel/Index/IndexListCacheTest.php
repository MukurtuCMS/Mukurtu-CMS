<?php

declare(strict_types = 1);

namespace Drupal\Tests\search_api\Kernel\Index;

use Drupal\entity_test\Entity\EntityTestMulRevChanged;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api\Task\TaskManagerInterface;
use Drupal\search_api_test\MethodOverrides;
use Drupal\search_api_test\PluginTestTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests correct caching for indexed items.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class IndexListCacheTest extends KernelTestBase {

  use PluginTestTrait;

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
   * The search server used for testing.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected ServerInterface $server;

  /**
   * The search index used for testing.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected IndexInterface $index;

  /**
   * The test entity type used in the test.
   *
   * @var string
   */
  protected string $testEntityTypeId = 'entity_test_mulrev_changed';

  /**
   * The task manager to use for the tests.
   *
   * @var \Drupal\search_api\Task\TaskManagerInterface
   */
  protected TaskManagerInterface $taskManager;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', [
      'search_api_item',
    ]);
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installConfig('search_api');

    $this->taskManager = $this->container->get('search_api.task_manager');

    User::create([
      'uid' => 1,
      'name' => 'root',
      'langcode' => 'en',
    ])->save();

    EntityTestMulRevChanged::create([
      'id' => 1,
      'name' => 'test 1',
    ])->save();
    EntityTestMulRevChanged::create([
      'id' => 2,
      'name' => 'test 2',
    ])->save();

    // Create a test server.
    $this->server = Server::create([
      'name' => 'Test Server',
      'id' => 'test_server',
      'status' => 1,
      'backend' => 'search_api_test',
    ]);
    $this->server->save();

    // Create a test index.
    $this->index = Index::create([
      'name' => 'Test Index',
      'id' => 'test_index',
      'status' => 1,
      'tracker_settings' => [
        'default' => [],
      ],
      'datasource_settings' => [
        'entity:user' => [],
        'entity:entity_test_mulrev_changed' => [],
      ],
      'processor_settings' => [
        'search_api_test' => [],
      ],
      'server' => $this->server->id(),
      'options' => ['index_directly' => FALSE],
    ]);
    $this->index->save();

    $this->taskManager->deleteTasks();
  }

  /**
   * Tests that indexing will correctly clear the cache even when errors occur.
   */
  public function testIndexingClearsIndexCache(): void {
    // Set a custom cache entry with the index's list tag.
    $cid = static::class . '::testIndexingClearsIndexCache()';
    \Drupal::cache()->set($cid, ['foo'], tags: ['search_api_list:' . $this->index->id()]);
    // Make sure the cache entry is there.
    $this->assertNotEmpty(\Drupal::cache()->get($cid));

    // Override test plugin methods to make sure one item is deleted from the
    // server and that indexing the other will throw a TypeError.
    $this->setMethodOverride('processor', 'alterIndexedItems', [$this, 'alterIndexedItemsOverride']);
    $this->setMethodOverride('backend', 'indexItems', [MethodOverrides::class, 'throwTypeError']);

    // Indexing lock should be available.
    $this->assertTrue(\Drupal::lock()->lockMayBeAvailable($this->index->getLockId()));
    try {
      $this->index->indexItems();
      $this->fail('Indexing did not throw a TypeError.');
    }
    catch (\TypeError) {
    }

    // Make sure that even though indexing failed with a TypeError, the lock was
    // released and the index list cache tag invalidated.
    $this->assertTrue(\Drupal::lock()->lockMayBeAvailable($this->index->getLockId()));
    $this->assertEmpty(\Drupal::cache()->get($cid));
  }

  /**
   * Provides a custom override for ProcessorInterface::alterIndexedItems().
   *
   * Alters the items to be indexed.
   *
   * @param \Drupal\search_api\Item\ItemInterface[] $items
   *   An array of items to be indexed, passed by reference.
   *
   * @see \Drupal\search_api\Processor\ProcessorInterface::alterIndexedItems()
   */
  public function alterIndexedItemsOverride(array &$items): void {
    $id = 'entity:entity_test_mulrev_changed/1:en';
    $this->assertArrayHasKey($id, $items);
    unset($items[$id]);
  }

}
