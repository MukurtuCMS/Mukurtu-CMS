<?php

namespace Drupal\search_api\Datasource;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\search_api\Annotation\SearchApiDatasource as SearchApiDatasourceAnnotation;
use Drupal\search_api\Attribute\SearchApiDatasource as SearchApiDatasourceAttribute;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\SearchApiPluginManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Manages datasource plugins.
 *
 * @see \Drupal\search_api\Attribute\SearchApiDatasource
 * @see \Drupal\search_api\Datasource\DatasourceInterface
 * @see \Drupal\search_api\Datasource\DatasourcePluginBase
 * @see plugin_api
 */
class DatasourcePluginManager extends SearchApiPluginManager {

  public function __construct(
    #[Autowire(service: 'container.namespaces')]
    \Traversable $namespaces,
    #[Autowire(service: 'cache.discovery')]
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    EventDispatcherInterface $eventDispatcher,
  ) {
    parent::__construct(
      'Plugin/search_api/datasource',
      $namespaces,
      $module_handler,
      $eventDispatcher,
      DatasourceInterface::class,
      SearchApiDatasourceAttribute::class,
      SearchApiDatasourceAnnotation::class,
    );

    $this->setCacheBackend($cache_backend, 'search_api_datasources');
    $this->alterInfo('search_api_datasource_info');
    $this->alterEvent(SearchApiEvents::GATHERING_DATA_SOURCES);
  }

}
