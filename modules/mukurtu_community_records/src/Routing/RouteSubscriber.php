<?php

namespace Drupal\mukurtu_community_records\Routing;

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
    // Replace the default node view controller with our community record
    // aware controller.
    if ($route = $collection->get('entity.node.canonical')) {
      $route->setDefault('_controller', '\Drupal\mukurtu_community_records\Controller\CommunityRecordsViewController::view');
    }
  }

}
