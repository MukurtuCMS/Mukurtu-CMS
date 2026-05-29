<?php

namespace Drupal\mukurtu_multipage_items\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.node.canonical')) {
      $defaultController = $route->getDefault('_controller');
      $config = \Drupal::service('config.factory')->getEditable('mukurtu_multipage_items.settings');
      // In Drupal 11.3.x, entity.node.canonical uses _entity_view rather than
      // _controller, so $defaultController may be null. Only persist it when it
      // has a value so the config-install default is preserved.
      if (!empty($defaultController)) {
        $config->set('_controller', $defaultController)->save();
      }
      $route->setDefault('_controller', '\Drupal\mukurtu_multipage_items\Controller\MultipageItemPageController::viewRedirect');
    }

    // Reroute display of the actual canonical MPI entity to the page view.
    if ($route = $collection->get('entity.multipage_item.canonical')) {
      $route->setDefault('_controller', '\Drupal\mukurtu_multipage_items\Controller\MultipageItemPageController::viewFirstPageEntity');
    }
  }

}
