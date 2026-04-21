<?php

namespace Drupal\search_api_solr\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\search_api\IndexInterface;

/**
 * Search API Solr event base class.
 */
abstract class AbstractIndexAwareEvent extends Event {

  /**
   * The Search API index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index.
   */
  public function __construct(IndexInterface $index) {
    $this->index = $index;
  }

  /**
   * Retrieves the index.
   */
  public function getIndex(): IndexInterface {
    return $this->index;
  }

}
