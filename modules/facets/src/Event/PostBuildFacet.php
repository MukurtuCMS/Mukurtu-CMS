<?php

namespace Drupal\facets\Event;

use Drupal\facets\FacetInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Implements the PostBuildFacet event.
 *
 * This event allows modules to modify a facet after it is built and before it
 * will be cached and rendered.
 */
final class PostBuildFacet extends Event {

  /**
   * The facet.
   *
   * @var \Drupal\facets\FacetInterface
   */
  private $facet;

  /**
   * PreAddFacetSourceCacheableDependencies constructor.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   */
  public function __construct(FacetInterface $facet) {
    $this->facet = $facet;
  }

  /**
   * Get the facet.
   *
   * @return \Drupal\facets\FacetInterface
   *   The facet.
   */
  public function getFacet(): FacetInterface {
    return $this->facet;
  }

  /**
   * Set the facet.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   */
  public function setFacet(FacetInterface $facet): void {
    $this->facet = $facet;
  }

}
