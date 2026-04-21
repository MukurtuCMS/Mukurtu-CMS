<?php

namespace Drupal\search_api_db\Event;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Wraps a query pre-execute event.
 */
final class QueryPreExecuteEvent extends Event {

  /**
   * The database query to be executed for the search.
   *
   * @var \Drupal\Core\Database\Query\SelectInterface
   */
  protected $dbQuery;

  /**
   * The search query that is being executed.
   *
   * @var \Drupal\search_api\Query\QueryInterface
   */
  protected $query;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $db_query
   *   The database query to be executed for the search.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search query that is being executed.
   */
  public function __construct(SelectInterface $db_query, QueryInterface $query) {
    $this->dbQuery = $db_query;
    $this->query = $query;
  }

  /**
   * Retrieves the database query.
   *
   * Will have "item_id" and "score" columns in its result.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The database query.
   */
  public function getDbQuery() {
    return $this->dbQuery;
  }

  /**
   * Sets the database query.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $dbQuery
   *   The new database query.
   *
   * @return $this
   */
  public function setDbQuery(SelectInterface $dbQuery) {
    $this->dbQuery = $dbQuery;
    return $this;
  }

  /**
   * Retrieves the search query being executed.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The search query being executed.
   */
  public function getQuery() {
    return $this->query;
  }

}
