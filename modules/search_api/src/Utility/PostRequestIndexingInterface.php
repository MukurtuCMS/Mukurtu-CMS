<?php

namespace Drupal\search_api\Utility;

/**
 * Provides an interface for the post-request indexing service.
 */
interface PostRequestIndexingInterface {

  /**
   * Registers items for indexing at the end of the page request.
   *
   * @param string $index_id
   *   The ID of the search index on which items should be indexed.
   * @param array $item_ids
   *   The IDs of the items to index.
   */
  public function registerIndexingOperation($index_id, array $item_ids);

  /**
   * Removes items from the indexing queue for the end of the page request.
   *
   * @param string $index_id
   *   The ID of the search index in question.
   * @param array $item_ids
   *   The IDs of the items that should not be indexed.
   */
  public function removeFromIndexing(string $index_id, array $item_ids): void;

  /**
   * Determines whether a post-request indexing operation is currently running.
   *
   * @return bool
   *   TRUE if a post-request indexing operation is currently happening, FALSE
   *   otherwise.
   */
  public function isIndexingActive(): bool;

}
