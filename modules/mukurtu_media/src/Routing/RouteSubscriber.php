<?php

namespace Drupal\mukurtu_media\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -200];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.media.collection')) {
      $requirements = $route->getRequirements();
      unset($requirements['_permission']);
      $requirements['_mukurtu_role'] = 'administrator+mukurtu_manager+protocol-protocol-community_record_steward+protocol-protocol-contributor+protocol-protocol-curator+protocol-protocol-language_contributor+protocol-protocol-language_steward+protocol-protocol-protocol_steward';
      $route->setRequirements($requirements);
    }
  }

}
