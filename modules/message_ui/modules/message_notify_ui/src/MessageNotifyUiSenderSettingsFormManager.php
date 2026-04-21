<?php

namespace Drupal\message_notify_ui;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the Message notify ui sender settings form plugin manager.
 */
class MessageNotifyUiSenderSettingsFormManager extends DefaultPluginManager {

  /**
   * Constructor for MessageNotifyUiSenderSettingsFormManager objects.
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
    parent::__construct('Plugin/MessageNotifyUiSenderSettingsForm', $namespaces, $module_handler, 'Drupal\message_notify_ui\MessageNotifyUiSenderSettingsFormInterface', 'Drupal\message_notify_ui\Annotation\MessageNotifyUiSenderSettingsForm');

    $this->alterInfo('message_notify_ui_message_notify_ui_sender_settings_form_info');
    $this->setCacheBackend($cache_backend, 'message_notify_ui_message_notify_ui_sender_settings_form_plugins');
  }

}
