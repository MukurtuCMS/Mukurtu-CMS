<?php

namespace Drupal\facets\Event;

use Drupal\facets\FacetInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Implements the get cache contexts event.
 *
 * This event allows modules to change the cache contexts of a facet if needed.
 */
final class GetFacetCacheContexts extends Event {

  /**
   * The cache contexts.
   *
   * @var string[]
   */
  private $cacheContexts;

  /**
   * The facet.
   *
   * @var \Drupal\facets\FacetInterface
   */
  private $facet;

  /**
   * GetCacheContexts constructor.
   *
   * @param string[] $cacheContexts
   *   The cache contexts.
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   */
  public function __construct($cacheContexts, FacetInterface $facet) {
    $this->cacheContexts = $cacheContexts;
    $this->facet = $facet;
  }

  /**
   * Get the cache contexts.
   *
   * @return string[]
   *   The cache contexts.
   */
  public function getCacheContexts(): array {
    return $this->cacheContexts ?? [];
  }

  /**
   * Get the cache contexts.
   *
   * @param string[] $cacheContexts
   *   The cache contexts.
   */
  public function setCacheContexts($cacheContexts): void {
    $this->cacheContexts = $cacheContexts;
  }

  /**
   * Get the facet.
   *
   * @return \Drupal\facets\FacetInterface
   *   The facet.
   */
  public function getFacet() {
    return $this->facet;
  }

}
