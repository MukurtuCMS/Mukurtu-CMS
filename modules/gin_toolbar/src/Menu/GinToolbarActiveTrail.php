<?php

namespace Drupal\gin_toolbar\Menu;

use Drupal\Core\Menu\MenuActiveTrail;

/**
 * Handles the active trail.
 */
class GinToolbarActiveTrail extends MenuActiveTrail {

  /**
   * {@inheritdoc}
   *
   * Change the active trail for node add/edit/view routes.
   */
  protected function doGetActiveTrailIds($menu_name) {
    $route_name = $this->routeMatch->getRouteName();
    $route_params = $this->routeMatch->getRawParameters()->all();

    // Content.
    if (in_array($route_name, [
      'system.admin_content',
      'node.add_page',
      'entity.node.canonical',
      'entity.node.edit_form',
    ])) {
      $link = $this->getLinkByRoutes($menu_name, [
            [$route_name, $route_params],
            ['system.admin_content', []],
      ]);
    }

    // Create Content.
    if ($route_name === 'node.add') {
      $link = $this->getLinkByRoutes($menu_name, [
            [$route_name, $route_params],
            ['node.add_page', []],
            ['system.admin_content', []],
      ]);
    }

    // Media.
    if (in_array($route_name, [
      'view.media_library.page',
      'entity.media.collection',
      'entity.media.add_page',
      'entity.media.add_form',
    ])) {
      $link = $this->getLinkByRoutes($menu_name, [
            [$route_name, $route_params],
            ['entity.media.collection', []],
      ]);
    }

    // Create Media.
    if ($route_name === 'entity.media.add_form') {
      $link = $this->getLinkByRoutes($menu_name, [
            [$route_name, $route_params],
            ['entity.media.add_page', []],
            ['entity.media.collection', []],
      ]);
    }

    // Files.
    if ($route_name === 'view.files.page_1') {
      $link = $this->getLinkByRoutes($menu_name, [
            [$route_name, $route_params],
            ['view.files.page_1 ', []],
      ]);
    }

    // Blocks.
    if ($route_name === 'entity.block_content.collection') {
      $link = $this->getLinkByRoutes($menu_name, [
            [$route_name, $route_params],
            ['entity.block_content.collection', []],
      ]);
    }

    // User.
    if (in_array($route_name, [
      'entity.user.collection',
      'user.role.settings',
    ])) {
      $link = $this->getLinkByRoutes($menu_name, [
            [$route_name, $route_params],
            ['entity.user.collection', []],
      ]);
    }

    // Reports.
    if (in_array($route_name, [
      'dblog.overview',
      'dblog.access_denied',
      'dblog.page_not_found',
      'dblog.search',
    ])) {
      $link = $this->getLinkByRoutes($menu_name, [
            [$route_name, $route_params],
            ['system.admin_reports', []],
      ]);
    }

    // Configuration.
    if (in_array($route_name, [
      'system.admin_config',
      'devel.admin_settings',
    ])) {
      $link = $this->getLinkByRoutes($menu_name, [
            [$route_name, $route_params],
            ['system.admin_config', []],
      ]);
    }

    if (!isset($link)) {
      return parent::doGetActiveTrailIds($menu_name);
    }

    $active_trail = ['' => ''];
    if ($parents = $this->menuLinkManager->getParentIds($link->getPluginId())) {
      $active_trail = $parents + $active_trail;
    }

    return $active_trail;
  }

  /**
   * {@inheritdoc}
   *
   * The active trail logic is different here, so the active trails should be
   * cached separately.
   */
  protected function getCid() {
    if (!isset($this->cid)) {
      $this->cid = 'gin-toolbar-' . parent::getCid();
    }

    return $this->cid;
  }

  /**
   * Get a possible link to base the active trail on.
   *
   * @param string $menu_name
   *   The name of the menu.
   * @param array $routes
   *   An array of route name & route params combinations in order of relevance.
   */
  protected function getLinkByRoutes(string $menu_name, array $routes) {
    foreach ($routes as $route) {
      [$route_name, $route_params] = $route;
      $links = $this->menuLinkManager->loadLinksByRoute($route_name, $route_params, $menu_name);

      foreach ($links as $link) {
        if (!empty($link->getParent())) {
          return $link;
        }
      }
    }

    return NULL;
  }

}
