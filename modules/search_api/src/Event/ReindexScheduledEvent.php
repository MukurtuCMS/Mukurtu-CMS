<?php

namespace Drupal\search_api\Event;

use Drupal\search_api\IndexInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Wraps a reindex scheduled event.
 */
final class ReindexScheduledEvent extends Event {

  /**
   * The index scheduled for reindexing.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * Boolean indicating whether the index was also cleared.
   *
   * @var bool
   */
  protected $clear;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index scheduled for reindexing.
   * @param bool $clear
   *   Boolean indicating whether the index was also cleared.
   */
  public function __construct(IndexInterface $index, bool $clear) {
    $this->index = $index;
    $this->clear = $clear;
  }

  /**
   * Retrieves the index scheduled for reindexing.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The index scheduled for reindexing.
   */
  public function getIndex(): IndexInterface {
    return $this->index;
  }

  /**
   * Retrieves a boolean indicating whether the index was also cleared.
   *
   * @return bool
   *   TRUE if the index was also cleared as part of the reindexing, FALSE
   *   otherwise.
   */
  public function isClear(): bool {
    return $this->clear;
  }

}
