<?php

namespace Drupal\facets\Plugin\facets\facet_source;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\Exception\Exception;
use Drupal\facets\Exception\InvalidQueryTypeException;
use Drupal\facets\FacetInterface;
use Drupal\facets\FacetSource\FacetSourcePluginBase;
use Drupal\facets\FacetSource\SearchApiFacetSourceInterface;
use Drupal\search_api\Backend\BackendInterface;
use Drupal\search_api\Display\DisplayPluginManagerInterface;
use Drupal\search_api\FacetsQueryTypeMappingInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Utility\QueryHelperInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a facet source based on a Search API display.
 *
 * @todo The support for non views displays might be removed from facets 3.x and
 *       moved into a sub or contributed module. So this class needs to become
 *       something like "SearchApiViewsDisplay" and a "SearchApiCustomDisplay"
 *       plugin needs to be provided by the sub or contributed module. At the
 *       moment we have switches within this class for example to get the cache
 *       metadata. Those need to be removed.
 *
 * @FacetsFacetSource(
 *   id = "search_api",
 *   deriver = "Drupal\facets\Plugin\facets\facet_source\SearchApiDisplayDeriver"
 * )
 */
class SearchApiDisplay extends FacetSourcePluginBase implements SearchApiFacetSourceInterface {

  /**
   * List of Search API cache plugins that works with Facets cache system.
   */
  const CACHEABLE_PLUGINS = [
    'search_api_tag',
    'search_api_time',
  ];

  /**
   * The search index the query should is executed on.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The display plugin manager.
   *
   * @var \Drupal\search_api\Display\DisplayPluginManagerInterface
   */
  protected $displayPluginManager;

  /**
   * The search result cache.
   *
   * @var \Drupal\search_api\Utility\QueryHelperInterface
   */
  protected $searchApiQueryHelper;

  /**
   * The clone of the current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The Drupal module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Indicates if the display is edited and saved.
   *
   * @var bool
   */
  protected $displayEditInProgress = FALSE;

  /**
   * Constructs a SearchApiBaseFacetSource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $query_type_plugin_manager
   *   The query type plugin manager.
   * @param \Drupal\search_api\Utility\QueryHelperInterface $search_results_cache
   *   The query type plugin manager.
   * @param \Drupal\search_api\Display\DisplayPluginManagerInterface $display_plugin_manager
   *   The display plugin manager.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object for the current request.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Core's module handler class.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PluginManagerInterface $query_type_plugin_manager, QueryHelperInterface $search_results_cache, DisplayPluginManagerInterface $display_plugin_manager, Request $request, ModuleHandlerInterface $moduleHandler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $query_type_plugin_manager);

    $this->searchApiQueryHelper = $search_results_cache;
    $this->displayPluginManager = $display_plugin_manager;
    $this->moduleHandler = $moduleHandler;
    $this->request = clone $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // If the Search API module is not enabled, we should just return an empty
    // object. This allows us to have this class in the module without having a
    // dependency on the Search API module.
    if (!$container->get('module_handler')->moduleExists('search_api')) {
      return new \stdClass();
    }

    $request_stack = $container->get('request_stack');

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.facets.query_type'),
      $container->get('search_api.query_helper'),
      $container->get('plugin.manager.search_api.display'),
      $request_stack->getMainRequest(),
      $container->get('module_handler')
    );
  }

  /**
   * Retrieves the Search API index for this facet source.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The search index.
   */
  public function getIndex() {
    if ($this->index === NULL) {
      $this->index = $this->getDisplay()->getIndex();
    }

    return $this->index;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath() {
    if ($this->isRenderedInCurrentRequest()) {
      return \Drupal::service('path.current')->getPath();
    }
    return $this->getDisplay()->getPath();
  }

  /**
   * Helper function to get arguments for views contextual filters.
   *
   * @return array
   *   Values of contextual filters.
   */
  private function extractArgumentsForViewDisplay(): array {
    $argumentValues = [];
    // For AJAX requests we cannot take the value the same way as for non-AJAX
    // requests because route is identified as Drupal AJAX and views arguments
    // are removed by Views.
    // @todo ajax review
    if ($this->request->isXmlHttpRequest()) {
      $argumentValues = explode('/', ($_REQUEST['view_args'] ?? ''));
    }
    else {
      $display = $this->getViewsDisplay()->getDisplay();

      // Display plugin which have a path, i.e. pages.
      // @see \Drupal\views\Plugin\views\display\PathPluginBase
      if ($display->hasPath()) {
        $viewUrlParameters = $display->getUrl()->getRouteParameters();
        if (!empty($viewUrlParameters)) {
          $parameters = [];
          foreach ($viewUrlParameters as $viewUrlParameter => $validator) {
            $parameters[] = $this->request->attributes->has($viewUrlParameter) ? $this->request->attributes->get($viewUrlParameter) : NULL;
          }

          // Add view parameters as arguments only if at least one of them
          // resolved to a value, otherwise let views handle the defaults.
          if (!empty(array_filter($parameters))) {
            $argumentValues = array_merge($argumentValues, $parameters);
          }
        }
      }
      // @todo Support other plugin types.
    }
    return $argumentValues;
  }

  /**
   * {@inheritdoc}
   */
  public function fillFacetsWithResults(array $facets) {
    $search_id = $this->getDisplay()->getPluginId();

    // Check if the results for this search id are already populated in the
    // query helper. This is usually the case for views displays that are
    // rendered on the same page, such as views_page.
    $results = $this->searchApiQueryHelper->getResults($search_id);

    $view = NULL;

    if ($results === NULL) {
      // If there are no results, we can check the Search API Display plugin has
      // configuration for views. If that configuration exists, we can execute
      // that view and try to use its results.
      $display_definition = $this->getDisplay()->getPluginDefinition();

      if (isset($display_definition['view_id'])) {
        $view = Views::getView($display_definition['view_id']);
        $view->setDisplay($display_definition['view_display']);
        $view->preExecute();
        $view->setArguments($this->extractArgumentsForViewDisplay());
        $view->execute();
        $results = $this->searchApiQueryHelper->getResults($search_id);
      }
    }

    if (!$results instanceof ResultSetInterface) {
      if ($view) {
        foreach ($facets as $facet) {
          // In case of an empty result we must inherit the cache metadata of
          // the query. It will know if no results is a valid "result" or a
          // temporary issue or an error and set the metadata accordingly.
          $facet->addCacheableDependency($view->getQuery());
        }
      }

      return;
    }

    // Get our facet data.
    $facet_results = $results->getExtraData('search_api_facets');

    // If no data is found in the 'search_api_facets' extra data, we can stop
    // execution here.
    if ($facet_results === []) {
      return;
    }

    // Loop over each facet and execute the build method from the given
    // query type.
    foreach ($facets as $facet) {
      $configuration = [
        'query' => $results->getQuery(),
        'facet' => $facet,
        'results' => $facet_results[$facet->getFieldIdentifier()] ?? [],
      ];

      // Get the Facet Specific Query Type, so we can process the results
      // using the build() function of the query type.
      $query_type = $this->queryTypePluginManager->createInstance($facet->getQueryType(), $configuration);
      $query_type->build();

      // Merge the runtime cache metadata of the query.
      $facet->addCacheableDependency($results->getQuery());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isRenderedInCurrentRequest() {
    return $this->getDisplay()->isRenderedInCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['field_identifier'] = [
      '#type' => 'select',
      '#options' => $this->getFields(),
      '#title' => $this->t('Field'),
      '#description' => $this->t('The field from the selected facet source which contains the data to build a facet for.<br> The field types supported are <strong>boolean</strong>, <strong>date</strong>, <strong>decimal</strong>, <strong>integer</strong> and <strong>string</strong>.'),
      '#required' => TRUE,
      '#default_value' => $this->facet->getFieldIdentifier(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields() {
    $indexed_fields = [];
    $index = $this->getIndex();

    $fields = $index->getFields();
    $server = $index->getServerInstance();
    $backend = $server->getBackend();

    foreach ($fields as $field) {
      $data_type_plugin_id = $field->getDataTypePlugin()->getPluginId();
      $query_types = $this->getQueryTypesForDataType($backend, $data_type_plugin_id);
      if (!empty($query_types)) {
        $indexed_fields[$field->getFieldIdentifier()] = $field->getLabel() . ' (' . $field->getPropertyPath() . ')';
      }
    }

    return $indexed_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryTypesForFacet(FacetInterface $facet) {
    // Get our Facets Field Identifier, which is equal to the Search API Field
    // identifier.
    $field_id = $facet->getFieldIdentifier();
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->getIndex();
    // Get the Search API Server.
    $server = $index->getServerInstance();
    // Get the Search API Backend.
    $backend = $server->getBackend();

    $fields = &drupal_static(__METHOD__, []);

    if (!isset($fields[$index->id()])) {
      $fields[$index->id()] = $index->getFields();
    }

    foreach ($fields[$index->id()] as $field) {
      if ($field->getFieldIdentifier() == $field_id) {
        return $this->getQueryTypesForDataType($backend, $field->getType());
      }
    }

    throw new InvalidQueryTypeException("No available query types were found for facet {$facet->getName()}");
  }

  /**
   * Retrieves the query types for a specified data type.
   *
   * Backend plugins can use this method to override the default query types
   * provided by the Search API with backend-specific ones that better use
   * features of that backend.
   *
   * @param \Drupal\search_api\Backend\BackendInterface $backend
   *   The backend that we want to get the query types for.
   * @param string $data_type_plugin_id
   *   The identifier of the data type.
   *
   * @return string[]
   *   An associative array with the plugin IDs of allowed query types, keyed by
   *   the generic name of the query_type.
   *
   * @see hook_facets_search_api_query_type_mapping_alter()
   */
  protected function getQueryTypesForDataType(BackendInterface $backend, $data_type_plugin_id) {
    $query_types = [];
    $query_types['string'] = 'search_api_string';

    // Add additional query types for specific data types.
    switch ($data_type_plugin_id) {
      case 'date':
        $query_types['date'] = 'search_api_date';
        $query_types['range'] = 'search_api_range';
        break;

      case 'decimal':
      case 'integer':
        $query_types['numeric'] = 'search_api_granular';
        $query_types['range'] = 'search_api_range';
        break;

    }

    // Find out if the backend implemented the Interface to retrieve specific
    // query types for the supported data_types.
    if ($backend instanceof FacetsQueryTypeMappingInterface) {
      $mapping = [
        $data_type_plugin_id => &$query_types,
      ];
      $backend->alterFacetQueryTypeMapping($mapping);
    }
    // Add it to a variable so we can pass it by reference. Alter hook complains
    // due to the property of the backend object is not passable by reference.
    $backend_plugin_id = $backend->getPluginId();

    // Let modules alter this mapping.
    \Drupal::moduleHandler()
      ->alter('facets_search_api_query_type_mapping', $backend_plugin_id, $query_types);

    return $query_types;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $display = $this->getDisplay();
    if ($display instanceof DependentPluginInterface) {
      return $display->calculateDependencies();
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplay() {
    return $this->displayPluginManager
      ->createInstance($this->pluginDefinition['display_id']);
  }

  /**
   * {@inheritdoc}
   */
  public function getViewsDisplay() {
    if (!$this->moduleHandler->moduleExists('views')) {
      return NULL;
    }

    $search_api_display_definition = $this->getDisplay()->getPluginDefinition();
    if (empty($search_api_display_definition['view_id'])) {
      return NULL;
    }

    $view_id = $search_api_display_definition['view_id'];
    $view_display = $search_api_display_definition['view_display'];

    $view = Views::getView($view_id);
    $view->setDisplay($view_display);
    return $view;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataDefinition($field_name) {
    $field = $this->getIndex()->getField($field_name);
    if ($field) {
      return $field->getDataDefinition();
    }
    throw new Exception("Field with name {$field_name} does not have a definition");
  }

  /**
   * {@inheritdoc}
   */
  public function getCount() {
    $search_id = $this->getDisplay()->getPluginId();
    if (!empty($search_id) && $this->searchApiQueryHelper->getResults($search_id) !== NULL) {
      return $this->searchApiQueryHelper->getResults($search_id)
        ->getResultCount();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    if ($views_display = $this->getViewsDisplay()) {
      if ($this->isDisplayEditInProgress()) {
        return [];
      }
      return $views_display
        ->getDisplay()
        ->getCacheMetadata()
        ->getCacheContexts();
    }

    // Custom display implementations should provide their own cache metadata.
    $display = $this->getDisplay();
    if ($display instanceof CacheableDependencyInterface) {
      return $display->getCacheContexts();
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if ($views_display = $this->getViewsDisplay()) {
      if ($this->isDisplayEditInProgress()) {
        return [];
      }
      return Cache::mergeTags(
        $views_display->getDisplay()->getCacheMetadata()->getCacheTags(),
        $views_display->getCacheTags()
      );
    }

    // Custom display implementations should provide their own cache metadata.
    $display = $this->getDisplay();
    if ($display instanceof CacheableDependencyInterface) {
      return $display->getCacheTags();
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    if ($views_display = $this->getViewsDisplay()) {
      if ($this->isDisplayEditInProgress()) {
        return CacheBackendInterface::CACHE_PERMANENT;
      }
      $cache_plugin = $views_display->getDisplay()->getPlugin('cache');
      return Cache::mergeMaxAges(
        $views_display->getDisplay()->getCacheMetadata()->getCacheMaxAge(),
        $cache_plugin ? $cache_plugin->getCacheMaxAge() : 0
      );
    }

    // Custom display implementations should provide their own cache metadata.
    $display = $this->getDisplay();
    if ($display instanceof CacheableDependencyInterface) {
      return $display->getCacheMaxAge();
    }

    // Caching is not supported.
    return 0;
  }

  /**
   * Register a facet.
   *
   * Alter views view cache metadata:
   *  - When view being re-saved it will collect all cache metadata from its
   * plugins, including cache plugin.
   *  - Search API cache plugin will pre-execute the query and collect cacheable
   * metadata from all facets and will pass it to the view.
   *
   * View will use collected cache tags to invalidate search results. And cache
   * context provided by the facet to vary results.
   *
   * @see \Drupal\views\Plugin\views\display\DisplayPluginBase::calculateCacheMetadata()
   * @see \Drupal\search_api\Plugin\views\cache\SearchApiCachePluginTrait::alterCacheMetadata()
   * @see \Drupal\facets\FacetManager\DefaultFacetManager::alterQuery()
   */
  public function registerFacet(FacetInterface $facet) {
    if (
      // On the config-sync or site install view will already have all required
      // cache tags, so don't react if it's already there.
      !in_array('config:' . $facet->getConfigDependencyName(), $this->getCacheTags())
      // Re-save it only if we know that views cache plugin works with facets.
      && in_array($this->getViewsDisplay()->getDisplay()->getOption('cache')['type'], static::CACHEABLE_PLUGINS)
    ) {
      $this->getViewsDisplay()->save();
    }
  }

  /**
   * Is the display currently edited and saved?
   *
   * @return bool
   *   Whether the display being edited.
   */
  public function isDisplayEditInProgress(): bool {
    return $this->displayEditInProgress;
  }

  /**
   * Set the state, that the display is currently edited and saved.
   *
   * @param bool $value
   *   Sets whether the display being edited.
   */
  public function setDisplayEditInProgress(bool $value): void {
    $this->displayEditInProgress = $value;
  }

}
