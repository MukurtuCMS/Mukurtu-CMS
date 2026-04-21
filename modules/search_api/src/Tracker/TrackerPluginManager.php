<?php

namespace Drupal\search_api\Tracker;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\search_api\Annotation\SearchApiTracker as SearchApiTrackerAnnotation;
use Drupal\search_api\Attribute\SearchApiTracker as SearchApiTrackerAttribute;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\SearchApiPluginManager;
use Drupal\search_api\Utility\Utility;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Manages tracker plugins.
 *
 * @see \Drupal\search_api\Attribute\SearchApiTracker
 * @see \Drupal\search_api\Tracker\TrackerPluginManager
 * @see \Drupal\search_api\Tracker\TrackerInterface
 * @see \Drupal\search_api\Tracker\TrackerPluginBase
 * @see plugin_api
 */
class TrackerPluginManager extends SearchApiPluginManager {

  public function __construct(
    #[Autowire(service: 'container.namespaces')]
    \Traversable $namespaces,
    #[Autowire(service: 'cache.discovery')]
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    EventDispatcherInterface $eventDispatcher,
  ) {
    parent::__construct(
      'Plugin/search_api/tracker',
      $namespaces,
      $module_handler,
      $eventDispatcher,
      TrackerInterface::class,
      SearchApiTrackerAttribute::class,
      SearchApiTrackerAnnotation::class,
    );

    $this->setCacheBackend($cache_backend, 'search_api_trackers');
    $this->alterInfo('search_api_tracker_info');
    $this->alterEvent(SearchApiEvents::GATHERING_TRACKERS);
  }

  /**
   * Retrieves an options list of available trackers.
   *
   * @return string[]
   *   An associative array mapping the IDs of all available tracker plugins to
   *   their labels.
   */
  public function getOptionsList() {
    $options = [];
    foreach ($this->getDefinitions() as $plugin_id => $plugin_definition) {
      $options[$plugin_id] = Utility::escapeHtml($plugin_definition['label']);
    }
    return $options;
  }

}
