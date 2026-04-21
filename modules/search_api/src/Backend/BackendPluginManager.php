<?php

namespace Drupal\search_api\Backend;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\search_api\Annotation\SearchApiBackend as SearchApiBackendAnnotation;
use Drupal\search_api\Attribute\SearchApiBackend as SearchApiBackendAttribute;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\SearchApiPluginManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Manages search backend plugins.
 *
 * @see \Drupal\search_api\Attribute\SearchApiBackend
 * @see \Drupal\search_api\Backend\BackendInterface
 * @see \Drupal\search_api\Backend\BackendPluginBase
 * @see plugin_api
 */
class BackendPluginManager extends SearchApiPluginManager {

  public function __construct(
    #[Autowire(service: 'container.namespaces')]
    \Traversable $namespaces,
    #[Autowire(service: 'cache.discovery')]
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    EventDispatcherInterface $eventDispatcher
  ) {
    parent::__construct(
      'Plugin/search_api/backend',
      $namespaces,
      $module_handler,
      $eventDispatcher,
      BackendInterface::class,
      SearchApiBackendAttribute::class,
      SearchApiBackendAnnotation::class,
    );

    $this->alterInfo('search_api_backend_info');
    $this->alterEvent(SearchApiEvents::GATHERING_BACKENDS);
    $this->setCacheBackend($cache_backend, 'search_api_backends');
  }

}
