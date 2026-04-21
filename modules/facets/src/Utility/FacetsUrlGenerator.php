<?php

namespace Drupal\facets\Utility;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\facets\Result\Result;
use Drupal\facets\UrlProcessor\UrlProcessorPluginManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Facets Url Generator service.
 */
class FacetsUrlGenerator {

  /**
   * The url processor plugin manager.
   *
   * @var \Drupal\facets\UrlProcessor\UrlProcessorPluginManager
   */
  protected $urlProcessorPluginManager;

  /**
   * The entity storage for facets.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $facetStorage;

  /**
   * Constructs a new instance of the FacetsUrlGenerator.
   *
   * @param \Drupal\facets\UrlProcessor\UrlProcessorPluginManager $urlProcessorPluginManager
   *   The url processor plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(UrlProcessorPluginManager $urlProcessorPluginManager, EntityTypeManagerInterface $entityTypeManager) {
    $this->urlProcessorPluginManager = $urlProcessorPluginManager;
    $this->facetStorage = $entityTypeManager->getStorage('facets_facet');
  }

  /**
   * Returns an URL object for a facet path.
   *
   * Example implementations:
   * @code
   * // Example to generate URL for 1 facet with 1 value.
   * \Drupal::service('facets.utility.url_generator')->getUrl(['tags' => [7]]);
   * // Example with multiple active filters.
   * $active_filters = ['tags' => [5, 7], 'color' => ['blue']];
   * \Drupal::service('facets.utility.url_generator')->getUrl($active_filters);
   * @endcode
   *
   * @param array $active_filters
   *   An array containing the active filters with key being the facet id and
   *   value being an array of raw values.
   * @param bool $keep_active
   *   TRUE if the currently active facets should be included to the URL or
   *   FALSE if they should be discarded. Defaults to TRUE.
   *
   * @return \Drupal\Core\Url|null
   *   A Url object for the given facet/value combination or null if no Result
   *   was returned by the UrlProcessor.
   *
   * @throws \InvalidArgumentException
   */
  public function getUrl(array $active_filters, $keep_active = TRUE): ?Url {
    // We use the first defined facet to load the url processor. As all facets
    // should be from the same facet source, this is fine.
    // This is because we don't support generating a url over multiple facet
    // sources.
    if (empty($active_filters)) {
      throw new \InvalidArgumentException("The active filters passed in are invalid. They should look like: ['facet_id' => ['value1', 'value2']]");
    }

    $facet_id = key($active_filters);
    if (!is_array($active_filters[$facet_id])) {
      throw new \InvalidArgumentException("The active filters passed in are invalid. They should look like: [$facet_id => ['value1', 'value2']]");
    }

    $facet = $this->facetStorage->load($facet_id);
    if ($facet === NULL) {
      throw new \InvalidArgumentException("The Facet $facet_id could not be loaded.");
    }

    // We need one raw value to build a Result. If we have the raw value in the
    // already active filters, it will be removed in the final result. So
    // instead we copy the value into a variable and unset it from the list.
    $raw_value = $active_filters[$facet_id][0];
    unset($active_filters[$facet_id][0]);

    /** @var \Drupal\facets\UrlProcessor\UrlProcessorInterface $url_processor */
    $url_processor = $this
      ->urlProcessorPluginManager
      ->createInstance($facet->getFacetSourceConfig()
        ->getUrlProcessorName(), ['facet' => $facet]);

    if ($keep_active) {
      $active_filters = array_merge_recursive($active_filters, $url_processor->getActiveFilters());
    }
    $url_processor->setActiveFilters($active_filters);

    // Use the url processor to create a result and return that item's url.
    $results = [new Result($facet, $raw_value, '', 0)];
    $processed_results = $url_processor->buildUrls($facet, $results);
    $result = reset($processed_results);
    if ($result) {
      return $result->getUrl();
    }
    return NULL;
  }

  /**
   * Gets the URL object for a request.
   *
   * This method statically caches the URL object for a request based on the
   * facet source path. This reduces subsequent calls to the processor from
   * having to regenerate the URL object. But every time you call this function
   * you'll get a fresh clone of the URL object that could be manipulated
   * individually.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param string $facet_source_path
   *   The facet source path.
   *
   * @return \Drupal\Core\Url
   *   The URL.
   */
  public function getUrlForRequest(Request $request, $facet_source_path = NULL): Url {
    /** @var \Drupal\Core\Url[] $requestUrlsByPath */
    $requestUrlsByPath = &drupal_static(__CLASS__ . __FUNCTION__, []);
    $request_uri = $request->getRequestUri();

    if (!array_key_exists($request_uri, $requestUrlsByPath)) {
      // Try to grab any route params from the original request. In case of
      // request path not having a matching route, Url generator will fail with.
      try {
        $requestUrl = Url::createFromRequest($request);
      }
      catch (ResourceNotFoundException $e) {
        // Bypass exception if no path available. Should be unreachable in
        // default FacetSource implementations, but you never know.
        if ($facet_source_path) {
          $requestUrl = Url::fromUserInput($facet_source_path, [
            'query' => [
              '_format' => \Drupal::request()->get('_format'),
            ],
          ]);
        }
        else {
          if ('system.404' === $request->attributes->get('_route')) {
            // It seems that a facet that is configured to be rendered without
            // its facet source is currently rendered on a dedicated "page not
            // found" page. If the facet source has a valid path we would not
            // land here but in the condition above. So the facet source must be
            // view block display or something similar. In this case we could
            // assume that such a facet takes care about its link target itself
            // and doesn't depend on the current path or the facet source path.
            // Let's provide the front page as valid fallback to let the facet
            // do its job.
            $requestUrl = Url::fromRoute('<front>');
          }
          else {
            throw $e;
          }
        }
      }

      $requestUrl->setOption('attributes', ['rel' => 'nofollow']);
      $requestUrlsByPath[$request_uri] = $requestUrl;
    }

    return clone $requestUrlsByPath[$request_uri];
  }

}
