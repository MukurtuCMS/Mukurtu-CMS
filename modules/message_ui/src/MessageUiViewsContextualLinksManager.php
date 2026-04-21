<?php

namespace Drupal\message_ui;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the Message UI views contextual links plugin manager.
 */
class MessageUiViewsContextualLinksManager extends DefaultPluginManager {

  /**
   * Constructor for MessageUiViewsContextualLinksManager objects.
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
    parent::__construct('Plugin/MessageUiViewsContextualLinks', $namespaces, $module_handler, 'Drupal\message_ui\MessageUiViewsContextualLinksInterface', 'Drupal\message_ui\Annotation\MessageUiViewsContextualLinks');

    $this->alterInfo('message_ui_message_ui_views_contextual_links_info');
    $this->setCacheBackend($cache_backend, 'message_ui_message_ui_views_contextual_links_plugins');
  }

}
