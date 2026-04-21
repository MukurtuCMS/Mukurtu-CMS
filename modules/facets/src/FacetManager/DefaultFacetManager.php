<?php

namespace Drupal\facets\FacetManager;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\facets\Event\PostBuildFacet;
use Drupal\facets\Exception\InvalidProcessorException;
use Drupal\facets\FacetInterface;
use Drupal\facets\FacetSource\FacetSourcePluginManager;
use Drupal\facets\Plugin\facets\facet_source\SearchApiDisplay;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\PostQueryProcessorInterface;
use Drupal\facets\Processor\PreQueryProcessorInterface;
use Drupal\facets\Processor\ProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginManager;
use Drupal\facets\QueryType\QueryTypePluginManager;

/**
 * The facet manager.
 *
 * The manager is responsible for interactions with the Search backend, such as
 * altering the query, it is also responsible for executing and building the
 * facet. It is also responsible for running the processors.
 */
class DefaultFacetManager {

  use StringTranslationTrait;

  /**
   * The query type plugin manager.
   *
   * @var \Drupal\facets\QueryType\QueryTypePluginManager
   *   The query type plugin manager.
   */
  protected $queryTypePluginManager;

  /**
   * The facet source plugin manager.
   *
   * @var \Drupal\facets\FacetSource\FacetSourcePluginManager
   */
  protected $facetSourcePluginManager;

  /**
   * The processor plugin manager.
   *
   * @var \Drupal\facets\Processor\ProcessorPluginManager
   */
  protected $processorPluginManager;

  /**
   * An array of facets that are being rendered.
   *
   * @var \Drupal\facets\FacetInterface[]
   *
   * @see \Drupal\facets\FacetInterface
   * @see \Drupal\facets\Entity\Facet
   */
  protected $facets = [];

  /**
   * The entity storage for facets.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|object
   */
  protected $facetStorage;

  /**
   * A static cache of already processed facets.
   *
   * @var \Drupal\facets\FacetInterface[]
   */
  protected $processedFacets = [];

  /**
   * A static cache of already built facets.
   *
   * @var \Drupal\facets\FacetInterface[]
   */
  protected $builtFacets = [];

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  private $routeMatch;

  /**
   * Constructs a new instance of the DefaultFacetManager.
   *
   * @param \Drupal\facets\QueryType\QueryTypePluginManager $query_type_plugin_manager
   *   The query type plugin manager.
   * @param \Drupal\facets\FacetSource\FacetSourcePluginManager $facet_source_manager
   *   The facet source plugin manager.
   * @param \Drupal\facets\Processor\ProcessorPluginManager $processor_plugin_manager
   *   The processor plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type plugin manager.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   The current route match.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(QueryTypePluginManager $query_type_plugin_manager, FacetSourcePluginManager $facet_source_manager, ProcessorPluginManager $processor_plugin_manager, EntityTypeManagerInterface $entity_type_manager, CurrentRouteMatch $route_match) {
    $this->queryTypePluginManager = $query_type_plugin_manager;
    $this->facetSourcePluginManager = $facet_source_manager;
    $this->processorPluginManager = $processor_plugin_manager;
    $this->facetStorage = $entity_type_manager->getStorage('facets_facet');
    $this->routeMatch = $route_match;
  }

  /**
   * Allows the backend to add facet queries to its native query object.
   *
   * This method is called by the implementing module to initialize the facet
   * display process.
   *
   * @param mixed $query
   *   The backend's native query object.
   * @param string $facetsource_id
   *   The facet source ID to process.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function alterQuery(&$query, $facetsource_id) {
    $query_is_cacheable = $query instanceof RefinableCacheableDependencyInterface;
    /** @var \Drupal\facets\FacetInterface[] $facets */
    $facets = $this->getFacetsByFacetSourceId($facetsource_id);

    foreach ($facets as $facet) {
      $processors = $facet->getProcessors();

      if (isset($processors['dependent_processor'])) {
        $conditions = $processors['dependent_processor']->getConfiguration();

        $enabled_conditions = [];
        foreach ($conditions as $facet_id => $condition) {
          if (empty($condition['enable'])) {
            continue;
          }
          $enabled_conditions[$facet_id] = $condition;
        }

        foreach ($enabled_conditions as $facet_id => $condition_settings) {
          if (!isset($facets[$facet_id]) || !$processors['dependent_processor']->isConditionMet($condition_settings, $facets[$facet_id])) {
            // The conditions are not met anymore, remove the active items.
            $facet->setActiveItems([]);

            // Remove the query parameter from other facets.
            foreach ($facets as $other_facet) {
              /** @var \Drupal\facets\UrlProcessor\UrlProcessorInterface $urlProcessor */
              $urlProcessor = $other_facet->getProcessors()['url_processor_handler']->getProcessor();
              $active_filters = $urlProcessor->getActiveFilters();
              unset($active_filters[$facet->id()]);
              $urlProcessor->setActiveFilters($active_filters);
            }
            // Add "dependend facet" cacheabillity to make sure that whenever
            // its preferences will change, for instance to "negate", query
            // results cache will take it to consideration.
            if ($query_is_cacheable) {
              $query->addCacheableDependency($facet);
            }
            // Don't convert this facet's active items into query conditions.
            // Continue with the next facet.
            continue(2);
          }
        }
      }

      /** @var \Drupal\facets\QueryType\QueryTypeInterface $query_type_plugin */
      $query_type_plugin = $this->queryTypePluginManager->createInstance(
        $facet->getQueryType(),
        [
          'query' => $query,
          'facet' => $facet,
        ]
      );
      $query_type_plugin->execute();
      // Merge cache metadata that gathered from facet and its processors.
      if ($query_is_cacheable) {
        if ($query->hasTag('alter_cache_metadata')) {
          $facet_source = $facet->getFacetSource();
          if ($facet_source instanceof SearchApiDisplay) {
            // Avoid a loop when saving a view. The Search API cache plugin for
            // views "preExecutes" a query to collect cache metadata from
            // modules that alter this query. Our SearchApiDisplay must not ask
            // the view for its cache metadata at this point which is in a
            // random state.
            $facet_source->setDisplayEditInProgress(TRUE);
          }
        }

        $query->addCacheableDependency($facet);
      }
    }
  }

  /**
   * Returns enabled facets for the searcher associated with this FacetManager.
   *
   * @return \Drupal\facets\FacetInterface[]
   *   An array of enabled facets.
   */
  public function getEnabledFacets() {
    return $this->facetStorage->loadMultiple();
  }

  /**
   * Returns currently rendered facets filtered by facetsource ID.
   *
   * @param string $facetsource_id
   *   The facetsource ID to filter by.
   *
   * @return \Drupal\facets\FacetInterface[]
   *   An array of enabled facets.
   *
   * @throws \Drupal\facets\Exception\InvalidProcessorException
   */
  public function getFacetsByFacetSourceId($facetsource_id) {
    // Immediately initialize the facets.
    $this->initFacets();
    $facets = [];
    foreach ($this->facets as $facet) {
      if ($facet->getFacetSourceId() === $facetsource_id) {
        $facets[$facet->id()] = $facet;
      }
    }
    return $facets;
  }

  /**
   * Initializes facet builds, sets the breadcrumb trail.
   *
   * Facets are built via FacetsFacetProcessor objects. Facets only need to be
   * processed, or built, once the FacetsFacetManager::processed semaphore is
   * set when this method is called ensuring that facets are built only once
   * regardless of how many times this method is called.
   *
   * @param string|null $facetsource_id
   *   The facetsource if of the currently processed facet.
   *
   * @throws \Drupal\facets\Exception\InvalidProcessorException
   *   Thrown when one of the defined processors is invalid.
   */
  public function processFacets($facetsource_id = NULL) {
    if ($facetsource_id === NULL) {
      foreach ($this->facets as $facet) {
        $current_facetsource_id = $facet->getFacetSourceId();
        $this->processFacets($current_facetsource_id);
      }
    }

    $unprocessedFacets = array_filter($this->facets, function ($item) use ($facetsource_id) {
      return $item->getFacetSourceId() === $facetsource_id && !isset($this->processedFacets[$facetsource_id][$item->id()]);
    });

    // All facets were already processed on a previous run, so no need to do so
    // again.
    if (count($unprocessedFacets) === 0) {
      return;
    }

    $this->updateResults($facetsource_id);

    foreach ($unprocessedFacets as $facet) {
      $processor_configs = $facet->getProcessorConfigs();
      foreach ($facet->getProcessorsByStage(ProcessorInterface::STAGE_POST_QUERY) as $processor) {
        $processor_config = $processor_configs[$processor->getPluginDefinition()['id']]['settings'];
        $processor_config['facet'] = $facet;
        /** @var \Drupal\facets\Processor\PostQueryProcessorInterface $post_query_processor */
        $post_query_processor = $this->processorPluginManager->createInstance($processor->getPluginDefinition()['id'], $processor_config);
        if (!$post_query_processor instanceof PostQueryProcessorInterface) {
          throw new InvalidProcessorException("The processor {$processor->getPluginDefinition()['id']} has a post_query definition but doesn't implement the required PostQueryProcessor interface");
        }
        $post_query_processor->postQuery($facet);
      }

      $this->processedFacets[$facetsource_id][$facet->id()] = $facet;
    }
  }

  /**
   * Initializes enabled facets.
   *
   * In this method all pre-query processors get called and their contents are
   * executed.
   *
   * @throws \Drupal\facets\Exception\InvalidProcessorException
   *   Thrown if one of the pre query processors is invalid.
   */
  protected function initFacets() {
    if (count($this->facets) > 0) {
      return;
    }

    $this->facets = $this->getEnabledFacets();
    foreach ($this->facets as $facet) {
      foreach ($facet->getProcessorsByStage(ProcessorInterface::STAGE_PRE_QUERY) as $processor) {
        /** @var \Drupal\facets\Processor\PreQueryProcessorInterface $pre_query_processor */
        $pre_query_processor = $facet->getProcessors()[$processor->getPluginDefinition()['id']];
        if (!$pre_query_processor instanceof PreQueryProcessorInterface) {
          throw new InvalidProcessorException("The processor {$processor->getPluginDefinition()['id']} has a pre_query definition but doesn't implement the required PreQueryProcessorInterface interface");
        }
        $pre_query_processor->preQuery($facet);
      }
    }
  }

  /**
   * Builds a facet.
   *
   * This method delegates to the relevant plugins in Build stage, the
   * processors that implement the  BuildProcessorInterface enabled on this
   * facet will run.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet we should build.
   *
   * @return \Drupal\facets\FacetInterface
   *   The built Facet.
   *
   * @throws \Drupal\facets\Exception\InvalidProcessorException
   *   Throws an exception when an invalid processor is linked to the facet.
   */
  protected function processBuild(FacetInterface $facet) {
    if (!isset($this->builtFacets[$facet->getFacetSourceId()][$facet->id()])) {
      // Immediately initialize the facets if they are not initiated yet.
      $this->initFacets();

      // It might be that the facet received here, is not the same as the
      // already loaded facets in the FacetManager.
      // For that reason, get the facet from the already loaded facets in the
      // static cache.
      $facet = $this->facets[$facet->id()];

      // For clarity, process facets is called each build.
      // The first facet therefor will trigger the processing. Note that
      // processing is done only once, so repeatedly calling this method will
      // not trigger the processing more than once.
      $this->processFacets($facet->getFacetSourceId());

      // Get the current results from the facets and let all processors that
      // trigger on the build step do their build processing.
      // @see \Drupal\facets\Processor\BuildProcessorInterface.
      // @see \Drupal\facets\Processor\SortProcessorInterface.
      $results = $facet->getResults();

      foreach ($facet->getProcessorsByStage(ProcessorInterface::STAGE_BUILD) as $processor) {
        if (!$processor instanceof BuildProcessorInterface) {
          throw new InvalidProcessorException("The processor {$processor->getPluginDefinition()['id']} has a build definition but doesn't implement the required BuildProcessorInterface interface");
        }
        $results = $processor->build($facet, $results);
      }

      // Trigger sort stage.
      $active_sort_processors = [];
      foreach ($facet->getProcessorsByStage(ProcessorInterface::STAGE_SORT) as $processor) {
        $active_sort_processors[] = $processor;
      }

      // Sort the actual results if we have enabled sort processors.
      if (!empty($active_sort_processors)) {
        $results = $this->sortFacetResults($active_sort_processors, $results);
      }

      $facet->setResults($results);

      $eventDispatcher = \Drupal::service('event_dispatcher');
      $event = new PostBuildFacet($facet);
      $eventDispatcher->dispatch($event);

      $this->builtFacets[$facet->getFacetSourceId()][$facet->id()] = $event->getFacet();
    }

    return $this->builtFacets[$facet->getFacetSourceId()][$facet->id()];
  }

  /**
   * Builds a facet and returns it as a renderable array.
   *
   * This method delegates to the relevant plugins to render a facet, it calls
   * out to a widget plugin to do the actual rendering when results are found.
   * When no results are found it calls out to the correct empty result plugin
   * to build a render array. Renderable array will include all facet plugins
   * cache metadata that were used to build this facet.
   *
   * Before doing any rendering, the processors that implement the
   * BuildProcessorInterface enabled on this facet will run.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet we should build.
   *
   * @return array
   *   Facet render arrays.
   *
   * @throws \Drupal\facets\Exception\InvalidProcessorException
   *   Throws an exception when an invalid processor is linked to the facet.
   */
  public function build(FacetInterface $facet) {
    if ($facet->getOnlyVisibleWhenFacetSourceIsVisible()) {
      // Block rendering and processing should be stopped when the facet source
      // is not available on the page. Returning an empty array here is enough
      // to halt all further processing.
      $facet_source = $facet->getFacetSource();
      if (is_null($facet_source) || !$facet_source->isRenderedInCurrentRequest()) {
        $build = [];
        $cacheableMetadata = new CacheableMetadata();
        $cacheableMetadata->addCacheableDependency($facet_source);
        $cacheableMetadata->applyTo($build);
        return $build;
      }
    }

    $built_facet = $this->processBuild($facet);
    // The build process might have returned a previously built and statically
    // cached instance of the facet object. So we need to ensure that the cache
    // metadata is updated on the outer object, too.
    $facet->addCacheableDependency($built_facet);

    // We include this build even if empty, it may contain attached libraries.
    /** @var \Drupal\facets\Widget\WidgetPluginInterface $widget */
    $widget = $built_facet->getWidgetInstance();
    $build = $widget->build($built_facet);
    // No results behavior handling. Return a custom text or false depending on
    // settings.
    if (empty($built_facet->getResults())) {
      $empty_behavior = $built_facet->getEmptyBehavior();
      switch ($empty_behavior['behavior'] ?? '') {
        case 'text':
          // @codingStandardsIgnoreStart
          $text = $this->t($empty_behavior['text'] ?? '');
          // @codingStandardsIgnoreEnd
          return [
            [
              0 => $build,
              '#type' => 'container',
              '#attributes' => [
                'data-drupal-facet-id' => $built_facet->id(),
                'class' => ['facet-empty'],
              ],
              'empty_text' => [
                '#type' => 'processed_text',
                '#text' => $text,
                '#format' => $empty_behavior['text_format'] ?? 'plain_text',
              ],
            ],
          ];

        case 'none':
          return [];

        case 'empty':
        default:
          return [$build];
      }
    }

    return [$build];
  }

  /**
   * Updates all facets of a given facet source with the raw results.
   *
   * @param string $facetsource_id
   *   The facet source ID of the currently processed facet.
   *
   * @throws \Drupal\facets\Exception\InvalidProcessorException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function updateResults($facetsource_id) {
    $facets = $this->getFacetsByFacetSourceId($facetsource_id);
    if ($facets) {
      // Clear the caches of processed results.
      unset($this->processedFacets[$facetsource_id]);
      unset($this->builtFacets[$facetsource_id]);

      /** @var \Drupal\facets\FacetSource\FacetSourcePluginInterface $facet_source_plugin */
      $facet_source_plugin = $this->facetSourcePluginManager->createInstance($facetsource_id);
      $facet_source_plugin->fillFacetsWithResults($facets);
    }
  }

  /**
   * Returns one of the processed facets.
   *
   * Returns one of the processed facets, this is a facet with filled results.
   * Keep in mind that if you want to have the facet's build processor executed,
   * call returnBuiltFacet() instead.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet to process with a collected plugins cache metadata.
   *
   * @return \Drupal\facets\FacetInterface|null
   *   The updated facet if it exists, NULL otherwise.
   *
   * @throws \Drupal\facets\Exception\InvalidProcessorException
   */
  public function returnProcessedFacet(FacetInterface $facet) {
    $this->processFacets($facet->getFacetSourceId());
    return !empty($this->facets[$facet->id()]) ? $this->facets[$facet->id()] : NULL;
  }

  /**
   * Returns one of the built facets.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet to process.
   *
   * @return \Drupal\facets\FacetInterface
   *   The built Facet object with a collected plugins cache metadata.
   */
  public function returnBuiltFacet(FacetInterface $facet) {
    return $this->processBuild($facet);
  }

  /**
   * Sort the facet results, and recurse to children to do the same.
   *
   * @param \Drupal\facets\Processor\SortProcessorInterface[] $active_sort_processors
   *   An array of sort processors.
   * @param \Drupal\facets\Result\ResultInterface[] $results
   *   An array of results.
   *
   * @return \Drupal\facets\Result\ResultInterface[]
   *   A sorted array of results.
   */
  protected function sortFacetResults(array $active_sort_processors, array $results) {
    uasort($results, function ($a, $b) use ($active_sort_processors) {
      $return = 0;
      foreach ($active_sort_processors as $sort_processor) {
        if ($return = $sort_processor->sortResults($a, $b)) {
          if ($sort_processor->getConfiguration()['sort'] == 'DESC') {
            $return *= -1;
          }
          break;
        }
      }
      return $return;
    });

    // Loop over the results and see if they have any children, if they do, fire
    // a request to this same method again with the children.
    foreach ($results as &$result) {
      if (!empty($result->getChildren())) {
        $children = $this->sortFacetResults($active_sort_processors, $result->getChildren());
        $result->setChildren($children);
      }
    }

    return $results;
  }

}
