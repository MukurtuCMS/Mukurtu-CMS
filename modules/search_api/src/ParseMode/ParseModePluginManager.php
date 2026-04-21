<?php

namespace Drupal\search_api\ParseMode;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\search_api\Annotation\SearchApiParseMode as SearchApiParseModeAnnotation;
use Drupal\search_api\Attribute\SearchApiParseMode as SearchApiParseModeAttribute;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\SearchApiPluginManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Manages parse mode plugins.
 *
 * @see \Drupal\search_api\Attribute\SearchApiParseMode
 * @see \Drupal\search_api\ParseMode\ParseModeInterface
 * @see \Drupal\search_api\ParseMode\ParseModePluginBase
 * @see plugin_api
 */
class ParseModePluginManager extends SearchApiPluginManager {

  public function __construct(
    #[Autowire(service: 'container.namespaces')]
    \Traversable $namespaces,
    #[Autowire(service: 'cache.discovery')]
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    EventDispatcherInterface $eventDispatcher,
  ) {
    parent::__construct(
      'Plugin/search_api/parse_mode',
      $namespaces,
      $module_handler,
      $eventDispatcher,
      ParseModeInterface::class,
      SearchApiParseModeAttribute::class,
      SearchApiParseModeAnnotation::class,
    );

    $this->setCacheBackend($cache_backend, 'search_api_parse_mode');
    $this->alterInfo('search_api_parse_mode_info');
    $this->alterEvent(SearchApiEvents::GATHERING_PARSE_MODES);
  }

  /**
   * Returns all known parse modes.
   *
   * @return \Drupal\search_api\ParseMode\ParseModeInterface[]
   *   An array of parse mode plugins, keyed by type identifier.
   */
  public function getInstances() {
    $parse_modes = [];

    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      if (class_exists($definition['class'])) {
        $parse_modes[$plugin_id] = $this->createInstance($plugin_id);
      }
    }

    return $parse_modes;
  }

  /**
   * Returns all parse modes known by the Search API as an options list.
   *
   * @return string[]
   *   An associative array with all parse mode's IDs as keys, mapped to their
   *   translated labels.
   *
   * @see \Drupal\search_api\ParseMode\ParseModePluginManager::getInstances()
   */
  public function getInstancesOptions() {
    $parse_modes = [];
    foreach ($this->getInstances() as $id => $info) {
      $parse_modes[$id] = $info->label();
    }
    return $parse_modes;
  }

}
