<?php

namespace Drupal\search_api_solr\Plugin\search_api\tracker;

use Drupal\search_api\Plugin\search_api\tracker\Basic;

/**
 * Provides a tracker implementation which uses a FIFO-like processing order.
 *
 *  @SearchApiTracker(
 *   id = "index_parallel",
 *   label = @Translation("Index parallel"),
 *   description = @Translation("Index parallel tracker which allows to index in parallel.")
 * )
 */
class IndexParallel extends Basic {

  const SAFETY_DISTANCE_FACTOR = 3;

  /**
   * @var int
   */
  protected $offset = 0;

  /**
   * @var int
   */
  protected $thread = 1;

  /**
   * @param int $offset
   *
   * @return void
   */
  public function setOffset(int $offset): void {
    $this->offset = $offset;
  }

  /**
   * @param int $thread
   *
   * @return void
   */
  public function setThread(int $thread): void {
    $this->thread = $thread;
  }

  public function getThread(): int {
    return $this->thread;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemainingItems($limit = -1, $datasource_id = NULL) {
    try {
      $select = $this->createRemainingItemsStatement($datasource_id);
      if ($limit >= 0) {
        $select->range($this->offset, $limit);
      }
      return $select->execute()->fetchCol();
    }
    catch (\Exception $e) {
      $this->logException($e);
      return [];
    }
  }

}
