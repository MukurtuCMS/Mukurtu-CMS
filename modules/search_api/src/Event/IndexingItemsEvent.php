<?php

namespace Drupal\search_api\Event;

use Drupal\search_api\IndexInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Wraps an indexing items event.
 */
final class IndexingItemsEvent extends Event {

  /**
   * The index on which items will be indexed.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The items that will be indexed.
   *
   * @var \Drupal\search_api\Item\ItemInterface[]
   */
  protected $items;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index on which items will be indexed.
   * @param \Drupal\search_api\Item\ItemInterface[] $items
   *   The items that will be indexed.
   */
  public function __construct(IndexInterface $index, array $items) {
    $this->index = $index;
    $this->items = $items;
  }

  /**
   * Retrieves the index on which items will be indexed.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The index on which items will be indexed.
   */
  public function getIndex(): IndexInterface {
    return $this->index;
  }

  /**
   * Retrieves the items that will be indexed.
   *
   * @return \Drupal\search_api\Item\ItemInterface[]
   *   The items that will be indexed.
   */
  public function getItems(): array {
    return $this->items;
  }

  /**
   * Sets the items that will be indexed.
   *
   * @param \Drupal\search_api\Item\ItemInterface[] $items
   *   The new items that will be indexed.
   */
  public function setItems(array $items) {
    $this->items = $items;
  }

}
