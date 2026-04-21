<?php

namespace Drupal\search_api\Display;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\search_api\Annotation\SearchApiDisplay as SearchApiDisplayAnnotation;
use Drupal\search_api\Attribute\SearchApiDisplay as SearchApiDisplayAttribute;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\SearchApiPluginManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Manages display plugins.
 *
 * @see \Drupal\search_api\Attribute\SearchApiDisplay
 * @see \Drupal\search_api\Display\DisplayInterface
 * @see \Drupal\search_api\Display\DisplayPluginBase
 * @see plugin_api
 */
class DisplayPluginManager extends SearchApiPluginManager implements DisplayPluginManagerInterface {

  /**
   * Static cache for the display plugins.
   *
   * @var \Drupal\search_api\Display\DisplayInterface[]|null
   *
   * @see \Drupal\search_api\Display\DisplayPluginManager::getInstances()
   */
  protected $displays = NULL;

  public function __construct(
    #[Autowire(service: 'container.namespaces')]
    \Traversable $namespaces,
    #[Autowire(service: 'cache.discovery')]
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    EventDispatcherInterface $eventDispatcher,
  ) {
    parent::__construct(
      'Plugin/search_api/display',
      $namespaces,
      $module_handler,
      $eventDispatcher,
      DisplayInterface::class,
      SearchApiDisplayAttribute::class,
      SearchApiDisplayAnnotation::class,
    );

    $this->setCacheBackend($cache_backend, 'search_api_displays');
    $this->alterInfo('search_api_displays');
    $this->alterEvent(SearchApiEvents::GATHERING_DISPLAYS);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstances() {
    if ($this->displays === NULL) {
      $this->displays = [];

      foreach ($this->getDefinitions() as $name => $display_definition) {
        if (class_exists($display_definition['class']) && empty($this->displays[$name])) {
          $display = $this->createInstance($name);
          $this->displays[$name] = $display;
        }
      }
    }

    return $this->displays;
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions() {
    parent::clearCachedDefinitions();

    $this->discovery = NULL;
  }

}
