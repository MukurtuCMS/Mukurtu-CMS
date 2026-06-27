<?php

namespace Drupal\mukurtu_workflows\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Replaces core's _content_moderation_latest_version access requirement.
 *
 * LatestRevisionCheck (core) only allows access via global Drupal permissions
 * ('view any unpublished content'). Mukurtu's node access is grant-based, so
 * granting that permission globally would bypass cultural protocol restrictions.
 * This subscriber swaps in _mukurtu_latest_version_access, which adds a third
 * path: protocol/language stewards for the node's protocols.
 */
class LatestVersionRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('entity.node.latest_version');
    if (!$route) {
      return;
    }
    $requirements = $route->getRequirements();
    unset($requirements['_content_moderation_latest_version']);
    $requirements['_mukurtu_latest_version_access'] = 'TRUE';
    $route->setRequirements($requirements);
  }

}
