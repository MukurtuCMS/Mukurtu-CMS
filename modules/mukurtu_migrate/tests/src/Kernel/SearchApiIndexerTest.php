<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_migrate\SearchApiIndexerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests queuing Search API indexing batches after a migration.
 */
#[Group('mukurtu_migrate')]
#[RunTestsInSeparateProcesses]
class SearchApiIndexerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'search_api',
    'search_api_db',
    'search_api_test_db',
    'search_api_test',
    'user',
    'system',
    'entity_test',
    'text',
    'mukurtu_migrate',
  ];

  /**
   * The search index storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');
    $this->installConfig('search_api');
    $this->installConfig('system');
    $this->config('system.site')
      ->set('uuid', $this->container->get('uuid')->generate())
      ->save();
    $this->installConfig(['search_api_test_db']);

    $this->storage = $this->container->get('entity_type.manager')->getStorage('search_api_index');
  }

  /**
   * Gets the indexer service under test.
   */
  protected function getIndexer(): SearchApiIndexerInterface {
    return $this->container->get(SearchApiIndexerInterface::class);
  }

  /**
   * Tests that an enabled index is queued for indexing.
   */
  public function testQueuesBatchForEnabledIndex(): void {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->storage->load('database_search_index');
    $this->assertTrue($index->status());
    $this->assertFalse($index->isReadOnly());

    $queued = $this->getIndexer()->queueIndexingForAllIndexes();
    $this->assertSame(1, $queued);

    $batch = batch_get();
    $this->assertCount(1, $batch['sets']);
    [, $arguments] = $batch['sets'][0]['operations'][0];
    /** @var \Drupal\search_api\IndexInterface $queued_index */
    $queued_index = $arguments[0];
    $this->assertSame('database_search_index', $queued_index->id());
  }

  /**
   * Tests that a disabled index is skipped.
   */
  public function testSkipsDisabledIndex(): void {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->storage->load('database_search_index');
    $index->setStatus(FALSE)->save();

    $queued = $this->getIndexer()->queueIndexingForAllIndexes();
    $this->assertSame(0, $queued);
    $this->assertEmpty(batch_get()['sets'] ?? []);
  }

  /**
   * Tests that a read-only index is skipped.
   */
  public function testSkipsReadOnlyIndex(): void {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->storage->load('database_search_index');
    $index->set('read_only', TRUE)->save();

    $queued = $this->getIndexer()->queueIndexingForAllIndexes();
    $this->assertSame(0, $queued);
    $this->assertEmpty(batch_get()['sets'] ?? []);
  }

  /**
   * Tests that no error occurs when no indexes exist.
   */
  public function testNoIndexesExist(): void {
    $this->storage->load('database_search_index')->delete();

    $queued = $this->getIndexer()->queueIndexingForAllIndexes();
    $this->assertSame(0, $queued);
    $this->assertEmpty(batch_get()['sets'] ?? []);
  }

  /**
   * Tests that an index that can't be batched is skipped, not fatal.
   *
   * A cron limit of 0 causes
   * \Drupal\search_api\Utility\IndexingBatchHelper::createBatch() to throw a
   * SearchApiException, exercising the same catch-and-continue path that a
   * held indexing lock would trigger in production.
   */
  public function testExceptionFromCreateBatchIsCaught(): void {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->storage->load('database_search_index');
    $index->setOption('cron_limit', 0)->save();

    $queued = $this->getIndexer()->queueIndexingForAllIndexes();
    $this->assertSame(0, $queued);
    $this->assertEmpty(batch_get()['sets'] ?? []);
  }

}
