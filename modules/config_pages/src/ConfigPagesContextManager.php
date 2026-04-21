<?php

namespace Drupal\config_pages;

use Drupal\config_pages\Attribute\ConfigPagesContext;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Context plugins manager.
 *
 * @package Drupal\config_pages
 */
class ConfigPagesContextManager extends DefaultPluginManager implements ConfigPagesContextManagerInterface {

  /**
   * Constructs an ConfigPagesContextManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ConfigPagesContext',
      $namespaces,
      $module_handler,
      'Drupal\config_pages\ConfigPagesContextInterface',
      ConfigPagesContext::class,
      'Drupal\config_pages\Annotation\ConfigPagesContext'
    );

    $this->alterInfo('config_pages_contexts_info');
    $this->setCacheBackend($cache_backend, 'config_pages_contexts');
  }

}
