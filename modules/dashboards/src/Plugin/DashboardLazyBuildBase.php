<?php

namespace Drupal\dashboards\Plugin;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Abstract class helper for lazy builds.
 */
abstract class DashboardLazyBuildBase extends DashboardBase implements DashboardLazyBuildInterface, TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function lazyBuildPreRender(string $pluginId, string $configuration): array {
    $configuration = Json::decode($configuration);
    $plugin = \Drupal::service('plugin.manager.dashboard')->createInstance($pluginId, $configuration);
    return static::lazyBuild($plugin, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderArray($configuration): array {
    return [
      '#lazy_builder' => [
        static::class . '::lazyBuildPreRender',
        [
          $this->getPluginId(),
          Json::encode($configuration),
        ],
      ],
      '#create_placeholder' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['lazyBuildPreRender'];
  }

}
