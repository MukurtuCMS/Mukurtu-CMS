<?php

namespace Drupal\mukurtu_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase
{

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection)
  {
    // Use a custom Mukurtu controller to restrict dashboard access to
    // authenticated users.
    if ($route = $collection->get('entity.dashboard.canonical')) {
      $route->setRequirement('_custom_access', '\Drupal\mukurtu_core\Controller\MukurtuDashboardController::access');
    }
  }
}
