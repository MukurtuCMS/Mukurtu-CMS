<?php

namespace Drupal\search_api_solr\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\search_api\Query\QueryInterface;
use Solarium\Core\Query\QueryInterface as SolariumQueryInterface;

/**
 * Search API Solr event base class.
 */
abstract class AbstractSearchApiQuerySolariumQueryEvent extends Event {

  /**
   * The search_api query.
   *
   * @var \Drupal\search_api\Query\QueryInterface
   */
  protected $searchApiQuery;

  /**
   * The solarium result.
   *
   * @var \Solarium\Core\Query\QueryInterface
   */
  protected $solariumQuery;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\search_api\Query\QueryInterface $search_api_query
   *   The search_api query.
   * @param \Solarium\Core\Query\QueryInterface $solarium_query
   *   The solarium query.
   */
  public function __construct(QueryInterface $search_api_query, SolariumQueryInterface $solarium_query) {
    $this->searchApiQuery = $search_api_query;
    $this->solariumQuery = $solarium_query;
  }

  /**
   * Retrieves the search_api query.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The created query.
   */
  public function getSearchApiQuery() : QueryInterface {
    return $this->searchApiQuery;
  }

  /**
   * Retrieves the solarium query.
   *
   * @return \Solarium\Core\Query\QueryInterface
   *   The solarium query.
   */
  public function getSolariumQuery(): SolariumQueryInterface {
    return $this->solariumQuery;
  }

}
