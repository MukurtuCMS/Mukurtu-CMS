<?php

namespace Drupal\mukurtu_gin_custom\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Marks Layout Builder configure-block routes as admin routes.
 *
 * Ensures the Gin admin theme is used when these routes are opened in a dialog
 * (e.g. via the front-end block edit feature).
 */
class MukurtuGinRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach (['layout_builder.update_block', 'layout_builder.add_block'] as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->setOption('_admin_route', TRUE);
      }
    }
  }

}
