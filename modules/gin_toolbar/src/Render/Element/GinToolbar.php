<?php

namespace Drupal\gin_toolbar\Render\Element;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Adds active trail to trail.
 *
 * * @package Drupal\gin_toolbar\Render\Element.
 */
class GinToolbar implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderTray'];
  }

  /**
   * Renders the toolbar's administration tray.
   *
   * This is a clone of core's toolbar_prerender_toolbar_administration_tray()
   * function, which adds active trail information and which uses setMaxDepth(4)
   * instead of setTopLevelOnly() in case the Admin Toolbar module is installed.
   *
   * @param array $build
   *   A renderable array.
   *
   * @return array
   *   The updated renderable array.
   *
   * @see toolbar_prerender_toolbar_administration_tray()
   */
  public static function preRenderTray(array $build) {
    if (!_gin_toolbar_module_is_active('toolbar')) {
      return $build;
    }
    $menu_tree = \Drupal::service('toolbar.menu_tree');
    $activeTrail = \Drupal::service('gin_toolbar.active_trail')
      ->getActiveTrailIds('admin');
    $parameters = (new MenuTreeParameters())
      ->setActiveTrail($activeTrail)
      ->setRoot('system.admin')
      ->excludeRoot()
      ->setTopLevelOnly()
      ->onlyEnabledLinks();

    if (\Drupal::moduleHandler()->moduleExists('admin_toolbar')) {
      $admin_toolbar_settings = \Drupal::config('admin_toolbar.settings');
      $max_depth = $admin_toolbar_settings->get('menu_depth') ?? 4;
      $parameters->setMaxDepth($max_depth);
    }

    $tree = $menu_tree->load('admin', $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ['callable' => 'gin_toolbar_tools_menu_navigation_links'],
    ];
    $tree = $menu_tree->transform($tree, $manipulators);

    $build['administration_menu'] = $menu_tree->build($tree);
    $build['#cache']['contexts'][] = 'route.menu_active_trails:admin';

    return $build;
  }

}
