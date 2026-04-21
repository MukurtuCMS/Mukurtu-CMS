<?php

namespace Drupal\dashboards\Plugin;

/**
 * Interface for lazy builds.
 */
interface DashboardLazyBuildInterface {

  /**
   * Callback for lazy build.
   *
   * @param \Drupal\dashboards\Plugin\DashboardBase $plugin
   *   Plugin.
   * @param array $configuration
   *   Configuration.
   *
   * @return array
   *   Return renderable array.
   */
  public static function lazyBuild(DashboardBase $plugin, array $configuration): array;

  /**
   * Helper for lazy build render.
   *
   * @param string $pluginId
   *   Dashboard plugin id.
   * @param string $configuration
   *   Serialized configuration.
   *
   * @return array
   *   Renderable array
   */
  public static function lazyBuildPreRender(string $pluginId, string $configuration): array;

}
