<?php

namespace Drupal\search_api\Event;

use Drupal\search_api\Query\QueryInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Wraps a query pre-execute event.
 */
final class QueryPreExecuteEvent extends Event {

  /**
   * The created query.
   *
   * @var \Drupal\search_api\Query\QueryInterface
   */
  protected $query;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The created query.
   */
  public function __construct(QueryInterface $query) {
    $this->query = $query;
  }

  /**
   * Retrieves the created query.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The created query.
   */
  public function getQuery(): QueryInterface {
    return $this->query;
  }

}
