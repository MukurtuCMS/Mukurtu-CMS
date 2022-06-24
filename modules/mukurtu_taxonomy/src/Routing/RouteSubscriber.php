<?php

namespace Drupal\mukurtu_taxonomy\Routing;

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
    // Use a custom Mukurtu controller to view taxonomy terms.
    if ($route = $collection->get('entity.taxonomy_term.canonical')) {
      $route->setDefault('_controller', '\Drupal\mukurtu_taxonomy\Controller\TaxonomyRecordViewController::build');
    }
  }

}
