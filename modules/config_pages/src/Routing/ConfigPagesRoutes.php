<?php

namespace Drupal\config_pages\Routing;

use Drupal\config_pages\Entity\ConfigPagesType;
use Symfony\Component\Routing\Route;

/**
 * Defines dynamic routes for Config Pages.
 */
class ConfigPagesRoutes {

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = [];

    // Declare dynamic routes for config pages entities.
    $types = ConfigPagesType::loadMultiple();
    foreach ($types as $cp_type) {
      $bundle = $cp_type->id();
      $label = $cp_type->get('label');
      $menu = $cp_type->get('menu');
      $path = $menu['path'] ?? '';

      if (!$path) {
        // Use module pre-defined path in case of user left menu item empty.
        $path = '/admin/structure/config_pages/' . $cp_type->id();
      }
      $routes['config_pages.' . $bundle] = new Route(
        $path,
        [
          '_controller' => '\Drupal\config_pages\Controller\ConfigPagesController::classInit',
          'config_pages_type' => $bundle,
          '_title_callback' => '\Drupal\config_pages\Controller\ConfigPagesController::getPageTitle',
          'label' => $label,
        ],
        [
          '_custom_access'  => '\Drupal\config_pages\Controller\ConfigPagesController::access',
        ],
        [
          '_admin_route' => TRUE,
        ]
      );
    }
    return $routes;
  }

}
