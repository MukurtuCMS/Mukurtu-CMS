<?php

namespace Drupal\facets\Event;

use Drupal\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Implements the active filters parsed event.
 *
 * This event allows modules to change the active filters parsed from URL if
 * needed.
 */
final class ActiveFiltersParsed extends Event {

  /**
   * The facet source id.
   *
   * @var string
   */
  private $facetsourceId;

  /**
   * The active filters.
   *
   * @var array
   */
  private $activeFilters;

  /**
   * The get parameters.
   *
   * @var \Symfony\Component\HttpFoundation\ParameterBag
   */
  private $queryParameters;

  /**
   * The facet parameter filter key.
   *
   * @var string
   */
  private $filterKey;

  /**
   * QueryStringCreated constructor.
   *
   * @param string $facetsource_id
   *   The facet source id.
   * @param array $activeFilters
   *   The active filters.
   * @param \Symfony\Component\HttpFoundation\ParameterBag $queryParameters
   *   The get parameters to use.
   * @param string $filter_key
   *   The facet filter key.
   */
  public function __construct($facetsource_id, array $activeFilters, ParameterBag $queryParameters, $filter_key) {
    $this->facetsourceId = $facetsource_id;
    $this->queryParameters = $queryParameters;
    $this->activeFilters = $activeFilters;
    $this->filterKey = $filter_key;
  }

  /**
   * Get the facet source id.
   *
   * @return string
   *   The facet source id.
   */
  public function getFacetSourceId() {
    return $this->facetsourceId;
  }

  /**
   * Get the active filters.
   *
   * Only to be used as context, because changing this will not result in any
   * changes to the final url.
   *
   * @return array
   *   The active filters.
   */
  public function getActiveFilters() {
    return $this->activeFilters;
  }

  /**
   * Set the active filters.
   *
   * @param array $activeFilters
   *   The active filters.
   */
  public function setActiveFilters(array $activeFilters) {
    $this->activeFilters = $activeFilters;
  }

  /**
   * Get the get parameters.
   *
   * @return \Symfony\Component\HttpFoundation\ParameterBag
   *   The get parameters.
   */
  public function getQueryParameters() {
    return $this->queryParameters;
  }

  /**
   * Get the facet parameter filter key.
   *
   * @return string
   *   The facet parameter filter key.
   */
  public function getFilterKey() {
    return $this->filterKey;
  }

}
