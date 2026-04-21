<?php

namespace Drupal\facets\Event;

use Drupal\facets\FacetInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Implements the get cache max age event.
 *
 * This event allows modules to change the cache max age of a facet if needed.
 */
final class GetFacetCacheMaxAge extends Event {

  /**
   * The cache max age.
   *
   * @var int
   */
  private $cacheMaxAge;

  /**
   * The facet.
   *
   * @var \Drupal\facets\FacetInterface
   */
  private $facet;

  /**
   * GetCacheMaxAge constructor.
   *
   * @param int $cacheMaxAge
   *   The cache max age.
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   */
  public function __construct($cacheMaxAge, FacetInterface $facet) {
    $this->cacheMaxAge = $cacheMaxAge;
    $this->facet = $facet;
  }

  /**
   * Get the cache max age.
   *
   * @return int
   *   The cache max age.
   */
  public function getCacheMaxAge(): int {
    return $this->cacheMaxAge ?? 0;
  }

  /**
   * Get the cache max age.
   *
   * @param int $cacheMaxAge
   *   The cache max age.
   */
  public function setCacheMaxAge($cacheMaxAge): void {
    $this->cacheMaxAge = $cacheMaxAge;
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
