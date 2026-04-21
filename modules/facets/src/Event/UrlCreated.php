<?php

namespace Drupal\facets\Event;

use Drupal\Core\Url;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\ResultInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Implements the url created event.
 *
 * This event allows modules to change the facet link's URL if needed.
 */
final class UrlCreated extends Event {

  /**
   * The get parameters.
   *
   * @var \Drupal\Core\Url
   */
  private $url;

  /**
   * The facet result.
   *
   * @var \Drupal\facets\Result\ResultInterface
   */
  private $facetResult;

  /**
   * The facet.
   *
   * @var \Drupal\facets\FacetInterface
   */
  private $facet;

  /**
   * UrlCreated constructor.
   *
   * @param \Drupal\Core\Url $url
   *   The facet link URL.
   * @param \Drupal\facets\Result\ResultInterface $facetResult
   *   The facet result.
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   */
  public function __construct(Url $url, ResultInterface $facetResult, FacetInterface $facet) {
    $this->url = $url;
    $this->facetResult = $facetResult;
    $this->facet = $facet;
  }

  /**
   * Get the URL.
   *
   * @return \Drupal\Core\Url
   *   The URL.
   */
  public function getUrl(): Url {
    return $this->url;
  }

  /**
   * Set the URL.
   *
   * @param \Drupal\Core\Url $url
   *   The URL to set.
   */
  public function setUrl(Url $url): void {
    $this->url = $url;
  }

  /**
   * Get the facet result.
   *
   * Only to be used as context, because changing this will not result in any
   * changes to the final url.
   *
   * @return \Drupal\facets\Result\ResultInterface
   *   The facet result.
   */
  public function getFacetResult() {
    return $this->facetResult;
  }

  /**
   * Get the facet.
   *
   * Only to be used as context, because changing this will not result in any
   * changes to the final url.
   *
   * @return \Drupal\facets\FacetInterface
   *   The facet.
   */
  public function getFacet() {
    return $this->facet;
  }

}
