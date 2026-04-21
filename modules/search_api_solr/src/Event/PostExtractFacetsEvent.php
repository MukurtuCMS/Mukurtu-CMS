<?php

namespace Drupal\search_api_solr\Event;

use Drupal\search_api\Query\QueryInterface;
use Solarium\QueryType\Select\Result\Result;

/**
 * Event after facets are extrected from the Solr response.
 */
final class PostExtractFacetsEvent extends AbstractSearchApiQuerySolariumResultEvent {

  /**
   * The facets array.
   *
   * @var array
   */
  protected $facets;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search_api query.
   * @param \Solarium\QueryType\Select\Result\Result $result
   *   The solarium result.
   * @param array $facets
   *   Reference to extracted facets array.
   */
  public function __construct(QueryInterface $query, Result $result, array &$facets) {
    parent::__construct($query, $result);

    $this->facets = &$facets;
  }

  /**
   * Retrieves the extracted facets.
   *
   * @return array
   *   The extracted facets array.
   */
  public function getFacets() : array {
    return $this->facets;
  }

  /**
   * Set the extracted facets.
   *
   * @param array $facets
   *   The new facets array.
   */
  public function setFacets(array $facets) {
    $this->facets = $facets;
  }

}
