<?php

namespace Drupal\facets\Plugin\facets\url_processor;

use Drupal\Core\Cache\UnchangingCacheableDependencyTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\facets\Event\ActiveFiltersParsed;
use Drupal\facets\Event\QueryStringCreated;
use Drupal\facets\Event\UrlCreated;
use Drupal\facets\FacetInterface;
use Drupal\facets\UrlProcessor\UrlProcessorPluginBase;
use Drupal\facets\Utility\FacetsUrlGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Query string URL processor.
 *
 * @FacetsUrlProcessor(
 *   id = "query_string",
 *   label = @Translation("Query string"),
 *   description = @Translation("Query string is the default Facets URL processor, and uses GET parameters, for example ?f[0]=brand:drupal&f[1]=color:blue")
 * )
 */
class QueryString extends UrlProcessorPluginBase {

  use UnchangingCacheableDependencyTrait;

  /**
   * A string of how to represent the facet in the url.
   *
   * @var string
   */
  protected $urlAlias;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The URL generator.
   *
   * @var \Drupal\facets\Utility\FacetsUrlGenerator
   */
  protected $urlGenerator;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Request $request, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $eventDispatcher, FacetsUrlGenerator $urlGenerator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $request, $entity_type_manager);
    $this->eventDispatcher = $eventDispatcher;
    $this->urlGenerator = $urlGenerator;
    $this->initializeActiveFilters();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('facets.utility.url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildUrls(FacetInterface $facet, array $results) {
    // No results are found for this facet, so don't try to create urls.
    if (empty($results)) {
      return [];
    }

    // First get the current list of get parameters.
    $get_params = $this->request->query;

    // When adding/removing a filter the number of pages may have changed,
    // possibly resulting in an invalid page parameter.
    if ($get_params->has('page')) {
      $current_page = $get_params->all()['page'];
      $get_params->remove('page');
    }

    // Set the url alias from the facet object.
    $this->urlAlias = $facet->getUrlAlias();

    // In case of a view page display, the facet source has a path, If the
    // source is a block, the path is null.
    $facet_source_path = $facet->getFacetSource()->getPath();
    $request = $this->getRequestByFacetSourcePath($facet_source_path);
    $requestUrl = $this->getUrlForRequest($facet_source_path, $request);

    $original_filter_params = [];
    foreach ($this->getActiveFilters() as $facet_id => $values) {
      $values = array_filter($values, static function ($it) {
        return $it !== NULL;
      });
      foreach ($values as $value) {
        $original_filter_params[] = $this->getUrlAliasByFacetId($facet_id, $facet->getFacetSourceId()) . $this->getSeparator() . $value;
      }
    }

    /** @var \Drupal\facets\Result\ResultInterface[] $results */
    foreach ($results as &$result) {
      // Reset the URL for each result.
      $url = clone $requestUrl;

      // Sets the url for children.
      if ($children = $result->getChildren()) {
        $this->buildUrls($facet, $children);
      }

      $filter_missing = '';
      if ($result->getRawValue() === NULL) {
        $filter_string = NULL;
      }
      elseif ($result->isMissing()) {
        $filter_missing = $this->urlAlias . $this->getSeparator() . '!(';
        $filter_string = $filter_missing . implode($this->getDelimiter(), $result->getMissingFilters()) . ')';
      }
      else {
        $filter_string = $this->urlAlias . $this->getSeparator() . $result->getRawValue();
      }
      $result_get_params = clone $get_params;

      $filter_params = $original_filter_params;

      // If the value is active, remove the filter string from the parameters.
      if ($result->isActive()) {
        foreach ($filter_params as $key => $filter_param) {
          if ($filter_param === $filter_string || ($filter_missing && str_starts_with($filter_param, $filter_missing))) {
            unset($filter_params[$key]);
          }
        }
        if ($facet->getUseHierarchy() && !$result->isMissing()) {
          $id = $result->getRawValue();

          // Disable child filters.
          foreach ($facet->getHierarchyInstance()->getNestedChildIds($id) as $child_id) {
            $filter_params = array_diff($filter_params, [$this->urlAlias . $this->getSeparator() . $child_id]);
          }
          if ($facet->getEnableParentWhenChildGetsDisabled()) {
            // Enable parent id again if exists.
            $parent_ids = $facet->getHierarchyInstance()->getParentIds($id);
            if (isset($parent_ids[0]) && $parent_ids[0]) {
              // Get the parents children.
              $child_ids = $facet->getHierarchyInstance()->getNestedChildIds($parent_ids[0]);

              // Check if there are active siblings.
              $active_sibling = FALSE;
              if ($child_ids) {
                foreach ($results as $result2) {
                  if ($result2->isActive() && $result2->getRawValue() != $id && in_array($result2->getRawValue(), $child_ids)) {
                    $active_sibling = TRUE;
                    continue;
                  }
                }
              }
              if (!$active_sibling) {
                $filter_params[] = $this->urlAlias . $this->getSeparator() . $parent_ids[0];
              }
            }
          }
        }

      }
      // If the value is not active, add the filter string.
      else {
        if ($filter_string !== NULL) {
          $filter_params[] = $filter_string;
        }

        $parents_and_child_ids = [];
        if ($facet->getUseHierarchy()) {
          $parent_ids = $facet->getHierarchyInstance()->getParentIds($result->getRawValue());
          $child_ids = $facet->getHierarchyInstance()->getNestedChildIds($result->getRawValue());
          $parents_and_child_ids = array_merge($parent_ids, $child_ids);

          if (!$facet->getKeepHierarchyParentsActive()) {
            // If hierarchy is active, unset parent trail and every child when
            // building the enable-link to ensure those are not enabled anymore.
            foreach ($parents_and_child_ids as $id) {
              $filter_params = array_diff($filter_params, [$this->urlAlias . $this->getSeparator() . $id]);
            }
          }
        }

        // Exclude currently active results from the filter params if we are in
        // the show_only_one_result mode.
        if ($facet->getShowOnlyOneResult()) {
          foreach ($results as $result2) {
            if ($result2->isActive()) {
              $id = $result2->getRawValue();
              if (!in_array($id, $parents_and_child_ids)) {
                $active_filter_string = $this->urlAlias . $this->getSeparator() . $id;
                foreach ($filter_params as $key2 => $filter_param2) {
                  if ($filter_param2 == $active_filter_string) {
                    unset($filter_params[$key2]);
                  }
                }
              }
            }
          }
        }
      }

      // Allow other modules to alter the result url query string built.
      $event = new QueryStringCreated($result_get_params, $filter_params, $result, $this->activeFilters, $facet);
      $this->eventDispatcher->dispatch($event);
      $filter_params = $event->getFilterParameters();

      asort($filter_params, \SORT_NATURAL);
      $result_get_params->set($this->filterKey, array_values($filter_params));

      if ($result_get_params->all() !== [$this->filterKey => []]) {
        $new_url_params = $result_get_params->all();

        if (empty($new_url_params[$this->filterKey])) {
          unset($new_url_params[$this->filterKey]);
        }

        // Facet links should be page-less.
        // See https://www.drupal.org/node/2898189.
        unset($new_url_params['page']);

        // Remove core wrapper format (e.g. render-as-ajax-response) parameters.
        // @todo ajax review
        unset($new_url_params[MainContentViewSubscriber::WRAPPER_FORMAT]);

        // Set the new url parameters.
        $url->setOption('query', $new_url_params);
      }

      // Allow other modules to alter the result url built.
      $event = new UrlCreated($url, $result, $facet);
      $this->eventDispatcher->dispatch($event);

      $result->setUrl($event->getUrl());
    }

    // Restore page parameter again. See https://www.drupal.org/node/2726455.
    if (isset($current_page)) {
      $get_params->set('page', $current_page);
    }
    return $results;
  }

  /**
   * Gets a request object based on the facet source path.
   *
   * If the facet's source has a path, we construct a request object based on
   * that path, as it may be different than the current request's. This method
   * statically caches the request object based on the facet source path so that
   * subsequent calls to this processor do not recreate the same request object.
   *
   * @param string $facet_source_path
   *   The facet source path.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  protected function getRequestByFacetSourcePath($facet_source_path) {
    $requestsByPath = &drupal_static(__CLASS__ . __FUNCTION__, []);
    if (!$facet_source_path) {
      return $this->request;
    }

    if (array_key_exists($facet_source_path, $requestsByPath)) {
      return $requestsByPath[$facet_source_path];
    }

    $request = Request::create($facet_source_path);
    $request->attributes->set('_format', $this->request->get('_format'));
    $requestsByPath[$facet_source_path] = $request;
    return $request;
  }

  /**
   * Gets the URL object for a request.
   *
   * This method delegates to the URL generator service. But we keep it for
   * backward compatibility for custom implementations that extend this class.
   *
   * @param string $facet_source_path
   *   The facet source path.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\Core\Url
   *   The URL.
   */
  protected function getUrlForRequest($facet_source_path, Request $request) {
    return $this->urlGenerator->getUrlForRequest($request, $facet_source_path);
  }

  /**
   * Initializes the active filters from the request query.
   *
   * Get all the filters that are active by checking the request query and store
   * them in activeFilters which is an array where key is the facet id and value
   * is an array of raw values.
   */
  protected function initializeActiveFilters() {
    if ($this->request->isXmlHttpRequest()) {
      $url_parameters = $this->request->request;
    }
    else {
      $url_parameters = $this->request->query;
    }
    // Get the active facet parameters.
    $active_params = $url_parameters->all()[$this->filterKey] ?? "";
    $facet_source_id = $this->configuration['facet']->getFacetSourceId();

    // When an invalid parameter is passed in the url, we can't do anything.
    if (!is_array($active_params)) {
      return;
    }

    $active_filters = [];
    // Explode the active params on the separator.
    foreach ($active_params as $param) {
      // Skip invalid user input.
      if (!is_string($param)) {
        continue;
      }

      $explosion = explode($this->getSeparator(), $param);
      $url_alias = array_shift($explosion);
      if ($facet_id = $this->getFacetIdByUrlAlias($url_alias, $facet_source_id)) {
        $value = '';
        while (count($explosion) > 0) {
          $value .= array_shift($explosion);
          if (count($explosion) > 0) {
            $value .= $this->getSeparator();
          }
        }
        if (!isset($active_filters[$facet_id])) {
          $active_filters[$facet_id] = [$value];
        }
        else {
          $active_filters[$facet_id][] = $value;
        }
      }
    }

    // Allow other modules to alter the parsed active filters.
    $event = new ActiveFiltersParsed($facet_source_id, $active_filters, $url_parameters, $this->filterKey);
    $this->eventDispatcher->dispatch($event);
    $this->activeFilters = $event->getActiveFilters();
  }

  /**
   * Gets the facet id from the url alias & facet source id.
   *
   * @param string $url_alias
   *   The url alias.
   * @param string $facet_source_id
   *   The facet source id.
   *
   * @return bool|string
   *   Either the facet id, or FALSE if that can't be loaded.
   */
  protected function getFacetIdByUrlAlias($url_alias, $facet_source_id) {
    $mapping = &drupal_static(__FUNCTION__);
    if (!isset($mapping[$facet_source_id][$url_alias])) {
      $storage = $this->entityTypeManager->getStorage('facets_facet');
      $facet = current($storage->loadByProperties(
        [
          'url_alias' => $url_alias,
          'facet_source_id' => $facet_source_id,
        ]
      ));
      if (!$facet) {
        return NULL;
      }
      $mapping[$facet_source_id][$url_alias] = $facet->id();
    }
    return $mapping[$facet_source_id][$url_alias];
  }

  /**
   * Gets the url alias from the facet id & facet source id.
   *
   * @param string $facet_id
   *   The facet id.
   * @param string $facet_source_id
   *   The facet source id.
   *
   * @return bool|string
   *   Either the url alias, or FALSE if that can't be loaded.
   */
  protected function getUrlAliasByFacetId($facet_id, $facet_source_id) {
    $mapping = &drupal_static(__FUNCTION__);
    if (!isset($mapping[$facet_source_id][$facet_id])) {
      $storage = $this->entityTypeManager->getStorage('facets_facet');
      $facet = current($storage->loadByProperties(
        [
          'id' => $facet_id,
          'facet_source_id' => $facet_source_id,
        ]
      ));
      if (!$facet) {
        return FALSE;
      }
      $mapping[$facet_source_id][$facet_id] = $facet->getUrlAlias();
    }
    return $mapping[$facet_source_id][$facet_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['url.query_args'];
  }

}
