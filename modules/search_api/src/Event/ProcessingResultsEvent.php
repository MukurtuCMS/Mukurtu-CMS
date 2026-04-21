<?php

namespace Drupal\search_api\Event;

use Drupal\search_api\Query\ResultSetInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Wraps a processing results event.
 */
final class ProcessingResultsEvent extends Event {

  /**
   * The search results.
   *
   * @var \Drupal\search_api\Query\ResultSetInterface
   */
  protected $results;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The search results.
   */
  public function __construct(ResultSetInterface $results) {
    $this->results = $results;
  }

  /**
   * Retrieves the search results.
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   The search results to alter.
   */
  public function getResults(): ResultSetInterface {
    return $this->results;
  }

  /**
   * Sets the search results.
   *
   * Usually, just making changes to the existing results object is sufficient,
   * and that is the preferred way of changing results. However, in certain
   * situations it can make sense to replace the results object with a
   * completely new object instead, in which case this method can be used.
   *
   * It should not be used unless really necessary, though, to avoid unintended
   * side effects (in case of modules that assume the results object will stay
   * unchanged).
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The new search results.
   */
  public function setResults(ResultSetInterface $results) {
    $this->results = $results;
  }

}
