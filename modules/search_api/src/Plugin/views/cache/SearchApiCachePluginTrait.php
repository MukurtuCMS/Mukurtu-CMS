<?php

namespace Drupal\search_api\Plugin\views\cache;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\QueryHelperInterface;

/**
 * Provides a trait to use in Views cache plugins for Search API queries.
 */
trait SearchApiCachePluginTrait {

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|null
   */
  protected $cacheBackend;

  /**
   * The cache contexts manager.
   *
   * @var \Drupal\Core\Cache\Context\CacheContextsManager|null
   */
  protected $cacheContextsManager;

  /**
   * The query helper.
   *
   * @var \Drupal\search_api\Utility\QueryHelperInterface|null
   */
  protected $queryHelper;

  /**
   * Retrieves the cache backend.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   *   The cache backend.
   */
  public function getCacheBackend() {
    return $this->cacheBackend ?: \Drupal::cache($this->resultsBin);
  }

  /**
   * Sets the cache backend.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The new cache backend.
   *
   * @return $this
   */
  public function setCacheBackend(CacheBackendInterface $cache_backend) {
    $this->cacheBackend = $cache_backend;
    return $this;
  }

  /**
   * Retrieves the cache contexts manager.
   *
   * @return \Drupal\Core\Cache\Context\CacheContextsManager
   *   The cache contexts manager.
   */
  public function getCacheContextsManager() {
    return $this->cacheContextsManager ?: \Drupal::service('cache_contexts_manager');
  }

  /**
   * Sets the cache contexts manager.
   *
   * @param \Drupal\Core\Cache\Context\CacheContextsManager $cache_contexts_manager
   *   The new cache contexts manager.
   *
   * @return $this
   */
  public function setCacheContextsManager(CacheContextsManager $cache_contexts_manager) {
    $this->cacheContextsManager = $cache_contexts_manager;
    return $this;
  }

  /**
   * Retrieves the query helper.
   *
   * @return \Drupal\search_api\Utility\QueryHelperInterface
   *   The query helper.
   */
  public function getQueryHelper() {
    return $this->queryHelper ?: \Drupal::service('search_api.query_helper');
  }

  /**
   * Sets the query helper.
   *
   * @param \Drupal\search_api\Utility\QueryHelperInterface $query_helper
   *   The new query helper.
   *
   * @return $this
   */
  public function setQueryHelper(QueryHelperInterface $query_helper) {
    $this->queryHelper = $query_helper;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheSet($type) {
    if ($type !== 'results') {
      parent::cacheSet($type);
      return;
    }

    $view = $this->getView();
    $query = $this->getQuery();

    // Get the max-age value according to the configuration of the view.
    $expire = $this->cacheSetMaxAge($type);
    // Get the max-age value of the executed query. A 3rd party module might
    // have set a different value on the query, especially in case of an error.
    // Search API advises backend implementations to set a max-age of "0" on
    // the query in case of errors before throwing exceptions.
    $query_max_age = $query->getCacheMaxAge();

    // If the max-age set on the query at runtime is anything else than
    // Cache::PERMANENT we must handle it.
    if ($query_max_age !== Cache::PERMANENT) {
      // If the max-age set on the query at runtime is lower than the value
      // configured in the view's caching settings, we must use the value
      // provided by the query. That mathematical rule covers the case of no
      // caching (max-age is "0") as well.
      // In case that Cache::PERMANENT is configured for the view, any runtime
      // value set on the query has precedence.
      if ($expire === Cache::PERMANENT || $query_max_age < $expire) {
        $expire = $query_max_age;
      }
    }

    if ($expire === 0) {
      // Don't cache the results.
      return;
    }

    if ($expire !== Cache::PERMANENT) {
      $expire += (int) $view->getRequest()->server->get('REQUEST_TIME');
    }
    $tags = Cache::mergeTags($this->getCacheTags(), $query->getCacheTags());

    // Unset the search_api_view query options to avoid serializing the full
    // ViewExecutable object in the cache. Keep the previous values so we can
    // restore them afterwards. (In 99% of cases both values will just be
    // $this->view but better to be extra-careful.)
    $search_api_query = $query->getSearchApiQuery();
    $view_in_query = $search_api_query->setOption('search_api_view', NULL);
    $view_in_query_original = $search_api_query->getOriginalQuery()->setOption('search_api_view', NULL);

    $result_set = $query->getSearchApiResults();
    if ($result_set === NULL) {
      return;
    }
    try {
      $data = [
        'result' => $view->result,
        'total_rows' => $view->total_rows ?? 0,
        'current_page' => $view->getCurrentPage(),
        'search_api results' => $result_set,
      ];
      $this->getCacheBackend()
        ->set($this->generateResultsKey(), $data, $expire, $tags);
    }
    finally {
      // We reset the search_api_view query options to their original values.
      $search_api_query->setOption('search_api_view', $view_in_query);
      $search_api_query->getOriginalQuery()->setOption('search_api_view', $view_in_query_original);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function cacheGet($type) {
    if ($type !== 'results') {
      return parent::cacheGet($type);
    }

    // Values to set: $view->result, $view->total_rows, $view->execute_time,
    // $view->current_page.
    $cache = $this->getCacheBackend()->get($this->generateResultsKey());
    if (!empty($cache->data['search_api results'])) {
      $cutoff = $this->cacheExpire($type);
      if (!$cutoff || $cache->created > $cutoff) {
        $view = $this->getView();
        $view->result = $cache->data['result'];
        $view->total_rows = $cache->data['total_rows'];
        $view->setCurrentPage($cache->data['current_page']);
        $view->execute_time = 0;

        // Trick Search API into believing a search happened, to make faceting
        // et al. work.
        /** @var \Drupal\search_api\Query\ResultSetInterface $results */
        $results = $cache->data['search_api results'];
        $this->getQueryHelper()->addResults($results);

        try {
          $query = $results->getQuery();
          $query->setOption('search_api_view', $view);
          $this->getQuery()->setSearchApiQuery($query);
        }
        catch (SearchApiException) {
          // Ignore.
        }

        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function generateResultsKey() {
    if (!isset($this->resultsKey)) {
      $this->getQuery()->getSearchApiQuery()?->preExecute();

      $view = $this->getView();
      $build_info = $view->build_info;

      $key_data = [
        'build_info' => $build_info,
        'pager' => [
          'page' => $view->getCurrentPage(),
          'items_per_page' => $view->getItemsPerPage(),
          'offset' => $view->getOffset(),
        ],
      ];

      // Vary the results key by the cache contexts of the display handler.
      // These cache contexts are calculated when the view is saved in the Views
      // UI and stored in the view config entity.
      $display_handler_cache_contexts = $this->displayHandler
        ->getCacheMetadata()
        ->getCacheContexts();
      $key_data += $this->getCacheContextsManager()
        ->convertTokensToKeys($display_handler_cache_contexts)
        ->getKeys();

      $this->resultsKey = $view->storage->id() . ':' . $this->displayHandler->display['id'] . ':results:' . Crypt::hashBase64(serialize($key_data));
    }

    return $this->resultsKey;
  }

  /**
   * Retrieves the view to which this plugin belongs.
   *
   * @return \Drupal\views\ViewExecutable
   *   The view.
   */
  protected function getView() {
    return $this->view;
  }

  /**
   * Retrieves the Search API Views query for the current view.
   *
   * @param bool $reset
   *   (optional) If TRUE, reset the query to its initial/unprocessed state.
   *   Should only be used in the context of a view being saved, never when the
   *   view is actually being executed.
   *
   * @return \Drupal\search_api\Plugin\views\query\SearchApiQuery
   *   The Search API Views query associated with the current view.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if there is no current Views query, or it is no Search API query.
   */
  protected function getQuery(bool $reset = FALSE): SearchApiQuery {
    if ($reset) {
      $view = $this->getView();
      $view_display = $view->getDisplay();
      $query = $view_display->getPlugin('query');
      $query->init($view, $view_display);
    }
    else {
      $query = $this->getView()->getQuery();
    }

    if ($query instanceof SearchApiQuery) {
      return $query;
    }
    throw new SearchApiException('No matching Search API Views query found in view.');
  }

  /**
   * {@inheritdoc}
   */
  public function alterCacheMetadata(CacheableMetadata $cache_metadata) {
    // A view can have multiple displays, but when information is gathered about
    // all the displays' metadata, it initializes the query plugin only once for
    // the first display. However, we need to collect cacheability metadata for
    // every single cacheable display in the view, thus we are resetting the
    // query to its original unprocessed state.
    $query = $this->getQuery(TRUE)->getSearchApiQuery();
    // In case the search index is disabled, or the query couldn't be created
    // for some other reason, there is nothing to do here.
    if (!$query) {
      return;
    }
    // Add a tag to the query to indicate that this is not a real search but the
    // save process of a view. Modules like facets can use this information to
    // not perform their normal search time tasks on this query. This is
    // especially important when an event handler would add caching information
    // to the query.
    $query->addTag('alter_cache_metadata');
    $query->preExecute();
    // Allow modules that alter the query to add their cache metadata to the
    // view.
    $cache_metadata->addCacheableDependency($query);
  }

}
