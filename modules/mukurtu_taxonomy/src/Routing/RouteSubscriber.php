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
      $route->setDefault('_title_callback', '\Drupal\mukurtu_taxonomy\Controller\TaxonomyRecordViewController::title');
      // The Redirect module sends this path to its alias before any access
      // check, and a term's alias contains its name (#2643).
      $route->setDefault('_disable_route_normalizer', TRUE);
    }
  }

}
