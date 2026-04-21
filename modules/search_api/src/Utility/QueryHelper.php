<?php

namespace Drupal\search_api\Utility;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\ParseMode\ParseModePluginManager;
use Drupal\search_api\Query\Query;
use Drupal\search_api\Query\ResultSetInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides methods for creating search queries and statically caching results.
 */
class QueryHelper implements QueryHelperInterface {

  /**
   * Storage for the results, keyed by request and search ID.
   *
   * @var \SplObjectStorage
   */
  protected $results;

  /**
   * NULL value to use as a key for the results storage.
   *
   * @var object
   */
  protected $null;

  public function __construct(
    protected RequestStack $requestStack,
    protected ModuleHandlerInterface $moduleHandler,
    #[Autowire(service: 'plugin.manager.search_api.parse_mode')]
    protected ParseModePluginManager $parseModeManager,
  ) {
    $this->results = new \SplObjectStorage();
    $this->null = (object) [];
  }

  /**
   * {@inheritdoc}
   */
  public function createQuery(IndexInterface $index, array $options = []) {
    $query = Query::create($index, $options);

    $query->setModuleHandler($this->moduleHandler);
    $query->setParseModeManager($this->parseModeManager);
    $query->setQueryHelper($this);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function addResults(ResultSetInterface $results) {
    $search_id = $results->getQuery()->getSearchId();
    $request = $this->getCurrentRequest();
    if (!isset($this->results[$request])) {
      $this->results[$request] = [
        $search_id => $results,
      ];
    }
    else {
      // It's not possible to directly assign array values to an array inside of
      // a \SplObjectStorage object. So we have to first retrieve the array,
      // then add the results to it, then store it again.
      $cache = $this->results[$request];
      $cache[$search_id] = $results;
      $this->results[$request] = $cache;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getResults($search_id) {
    return $this->results[$this->getCurrentRequest()][$search_id] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllResults() {
    return $this->results[$this->getCurrentRequest()] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function removeResults($search_id) {
    $results = $this->results[$this->getCurrentRequest()];
    unset($results[$search_id]);
    $this->results[$this->getCurrentRequest()] = $results;
  }

  /**
   * Retrieves the current request.
   *
   * If there is no current request, instead of returning NULL this will instead
   * return a unique object to be used in lieu of a NULL key.
   *
   * @return \Symfony\Component\HttpFoundation\Request|object
   *   The current request, if present; or this object's representation of the
   *   NULL key.
   */
  protected function getCurrentRequest() {
    return $this->requestStack->getCurrentRequest() ?: $this->null;
  }

}
