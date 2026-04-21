<?php

namespace Drupal\search_api\Event;

use Drupal\search_api\IndexInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Wraps an items indexed event.
 */
final class ItemsIndexedEvent extends Event {

  /**
   * The index that indexed the items.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The processed IDs.
   *
   * @var array
   */
  protected $processedIds;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index that indexed the items.
   * @param int[] $processedIds
   *   The processed IDs.
   */
  public function __construct(IndexInterface $index, array $processedIds) {
    $this->index = $index;
    $this->processedIds = $processedIds;
  }

  /**
   * Retrieves the index that indexed the items.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The used index.
   */
  public function getIndex(): IndexInterface {
    return $this->index;
  }

  /**
   * Retrieves the processed IDs.
   *
   * @return int[]
   *   An array containing the successfully indexed items' IDs.
   */
  public function getProcessedIds(): array {
    return $this->processedIds;
  }

}
