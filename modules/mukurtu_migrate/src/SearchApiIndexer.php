<?php

declare(strict_types=1);

namespace Drupal\mukurtu_migrate;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\IndexingBatchHelperInterface;

/**
 * Queues Search API indexing batches for all indexes.
 */
final class SearchApiIndexer implements SearchApiIndexerInterface {

  /**
   * Constructs a new SearchApiIndexer.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\search_api\Utility\IndexingBatchHelperInterface $indexingBatchHelper
   *   Search API's indexing batch helper service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger channel factory.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected IndexingBatchHelperInterface $indexingBatchHelper,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function queueIndexingForAllIndexes(): int {
    /** @var \Drupal\search_api\IndexInterface[] $indexes */
    $indexes = $this->entityTypeManager->getStorage('search_api_index')->loadMultiple();

    $queued = 0;
    foreach ($indexes as $index) {
      if ($this->queueIndexingForIndex($index)) {
        $queued++;
      }
    }
    return $queued;
  }

  /**
   * Queues an indexing batch for a single index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index to queue.
   *
   * @return bool
   *   TRUE if a batch was queued for the index, FALSE otherwise.
   */
  private function queueIndexingForIndex(IndexInterface $index): bool {
    if (!$index->status() || $index->isReadOnly()) {
      return FALSE;
    }

    try {
      $this->indexingBatchHelper->createBatch($index);
      return TRUE;
    }
    catch (SearchApiException $e) {
      $this->loggerFactory->get('mukurtu_migrate')->error('Unable to queue indexing for Search API index "@index": @message', [
        '@index' => $index->label(),
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

}
