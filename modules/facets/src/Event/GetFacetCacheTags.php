<?php

namespace Drupal\facets\Event;

use Drupal\facets\FacetInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Implements the get cache tags event.
 *
 * This event allows modules to change the cache tags of a facet if needed.
 */
final class GetFacetCacheTags extends Event {

  /**
   * The cache tags.
   *
   * @var string[]
   */
  private $cacheTags;

  /**
   * The facet.
   *
   * @var \Drupal\facets\FacetInterface
   */
  private $facet;

  /**
   * GetCacheTags constructor.
   *
   * @param string[] $cacheTags
   *   The cache tags.
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   */
  public function __construct($cacheTags, FacetInterface $facet) {
    $this->cacheTags = $cacheTags;
    $this->facet = $facet;
  }

  /**
   * Get the cache tags.
   *
   * @return string[]
   *   The cache tags.
   */
  public function getCacheTags(): array {
    return $this->cacheTags ?? [];
  }

  /**
   * Get the cache tags.
   *
   * @param string[] $cacheTags
   *   The cache tags.
   */
  public function setCacheTags($cacheTags): void {
    $this->cacheTags = $cacheTags;
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
