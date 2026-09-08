<?php

namespace Drupal\mukurtu_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase
{

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
  protected function alterRoutes(RouteCollection $collection)
  {
    // Use a custom Mukurtu controller to restrict dashboard access to
    // authenticated users. Remove the default permission-gated entity access
    // requirement so the custom check is the sole gate; otherwise the two
    // requirements AND together and block any authenticated user who lacks
    // the dashboards module's per-dashboard view permission.
    if ($route = $collection->get('entity.dashboard.canonical')) {
      $requirements = $route->getRequirements();
      unset($requirements['_entity_access']);
      $route->setRequirements($requirements);
      $route->setRequirement('_custom_access', '\Drupal\mukurtu_core\Controller\MukurtuDashboardController::access');
    }

    // Relabel the user account cancel route to reflect that both blocking
    // and deletion are available options.
    if ($route = $collection->get('entity.user.cancel_form')) {
      $route->setDefault('_title', 'Block or delete account');
    }

    // Replace the 'access content overview' permission on /admin/content with
    // a Mukurtu role check so protocol role members can access it.
    // Also point the route at the Mukurtu content overview view so its
    // VBO configuration (not the core node_bulk_form) is used.
    if ($route = $collection->get('system.admin_content')) {
      $requirements = $route->getRequirements();
      unset($requirements['_permission']);
      $requirements['_mukurtu_role'] = 'administrator+mukurtu_manager+mukurtu_roundtrip_manager+protocol-protocol-community_record_steward+protocol-protocol-contributor+protocol-protocol-curator+protocol-protocol-language_contributor+protocol-protocol-language_steward+protocol-protocol-protocol_steward';
      $route->setRequirements($requirements);
      $route->setDefault('view_id', 'mukurtu_manage_all_content');
      $route->setDefault('display_id', 'mukurtu_manage_content');
    }

    // Restrict the Message Subscribe UI "Subscriptions" page/tab to admins,
    // and render it in the admin theme like the rest of Mukurtu's admin
    // tooling. SubscriptionController::tabAccess() otherwise lets any
    // authenticated user view their own /user/{uid}/message-subscribe page
    // regardless of the 'administer message subscribe' permission, via a
    // self-view bypass. That page is internal admin tooling, not end-user
    // facing.
    foreach (['message_subscribe_ui.tab', 'message_subscribe_ui.tab.flag'] as $route_name) {
      if ($route = $collection->get($route_name)) {
        $requirements = $route->getRequirements();
        unset($requirements['_custom_access']);
        $requirements['_permission'] = 'administer message subscribe';
        $route->setRequirements($requirements);
        $route->setOption('_admin_route', TRUE);
      }
    }
  }
}
