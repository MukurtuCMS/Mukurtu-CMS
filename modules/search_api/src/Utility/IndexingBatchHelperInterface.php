<?php

namespace Drupal\search_api\Utility;

use Drupal\search_api\IndexInterface;

/**
 * Provides an interface for the index batch helper service.
 */
interface IndexingBatchHelperInterface {

  /**
   * Creates an indexing batch for a given search index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index for which items should be indexed.
   * @param int|null $batch_size
   *   (optional) Number of items to index per batch. Defaults to the cron limit
   *   set for the index.
   * @param int $limit
   *   (optional) Maximum number of items to index. Defaults to indexing all
   *   remaining items.
   * @param int $time_limit
   *   (optional) The maximum number of seconds allowed to run indexing for a
   *   given index. Defaults to -1 (no limit).
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if the batch could not be created.
   */
  public function createBatch(
    IndexInterface $index,
    ?int $batch_size = NULL,
    int $limit = -1,
    int $time_limit = -1,
  ): void;

  /**
   * Processes an index batch operation.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index on which items should be indexed.
   * @param int $batch_size
   *   The maximum number of items to index per batch pass.
   * @param int $limit
   *   The maximum number of items to index in total, or -1 to index all items.
   * @param int $time_limit
   *   (optional) The maximum number of seconds allowed to run indexing, or -1
   *   to not have any limit. Defaults to -1 (no limit).
   * @param array|\ArrayAccess $context
   *   The context of the current batch, as defined in the @link batch Batch
   *   operations @endlink documentation.
   */
  public function process(
    IndexInterface $index,
    int $batch_size,
    int $limit,
    int $time_limit,
    array|\ArrayAccess &$context,
  ): void;

  /**
   * Finishes the indexing batch.
   *
   * @param bool $success
   *   TRUE if the batch succeeded, FALSE otherwise.
   * @param array $results
   *   The context's "results" key.
   * @param array $operations
   *   The executed operations.
   */
  public function finish(
    bool $success,
    array $results,
    array $operations,
  ): void;

}
