<?php

namespace Drupal\search_api;

use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\search_api\Utility\IndexingBatchHelperInterface;

@trigger_error('\Drupal\search_api\Utility\IndexBatchHelper is deprecated in search_api:8.x-1.40 and is removed from search_api:2.0.0. Use the "search_api.indexing_batch_helper" service instead. See https://www.drupal.org/node/3552675', E_USER_DEPRECATED);

/**
 * Provides helper methods for indexing items using Drupal's Batch API.
 *
 * @deprecated in search_api:8.x-1.40 and is removed from search_api:2.0.0. Use
 *   the "search_api.indexing_batch_helper" service instead.
 *
 * @see https://www.drupal.org/node/3552675
 */
class IndexBatchHelper {

  /**
   * Sets the translation manager.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation_manager
   *   The new translation manager.
   */
  public static function setStringTranslation(TranslationInterface $translation_manager) {
  }

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
  public static function create(
    IndexInterface $index,
    $batch_size = NULL,
    $limit = -1,
    int $time_limit = -1,
  ) {
    static::service()->createBatch($index, $batch_size, $limit, $time_limit);
  }

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
  public static function process(
    IndexInterface $index,
    $batch_size,
    $limit,
    int $time_limit,
    &$context,
  ) {
    static::service()->process($index, $batch_size, $limit, $time_limit, $context);
  }

  /**
   * Finishes an index batch.
   */
  public static function finish($success, $results, $operations) {
    static::service()->finish($success, $results, $operations);
  }

  /**
   * Retrieves the index batch helper service.
   *
   * @return \Drupal\search_api\Utility\IndexingBatchHelperInterface
   *   The index batch helper service.
   */
  protected static function service(): IndexingBatchHelperInterface {
    return \Drupal::service('search_api.indexing_batch_helper');
  }

}
