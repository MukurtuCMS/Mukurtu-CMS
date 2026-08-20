<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\mukurtu_core\Routing\RouteSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests that the Message Subscribe UI routes are locked down to admins.
 *
 * @see \Drupal\mukurtu_core\Routing\RouteSubscriber::alterRoutes()
 * @see \Drupal\message_subscribe_ui\Controller\SubscriptionController::tabAccess()
 */
#[Group('mukurtu_core')]
class RouteSubscriberMessageSubscribeAccessTest extends UnitTestCase {

  /**
   * Builds a collection carrying both Message Subscribe UI routes.
   */
  private function messageSubscribeRouteCollection(): RouteCollection {
    $collection = new RouteCollection();

    $tab = new Route('/user/{user}/message-subscribe');
    $tab->setRequirement('_custom_access', '\Drupal\message_subscribe_ui\Controller\SubscriptionController::tabAccess');
    $collection->add('message_subscribe_ui.tab', $tab);

    $flag_tab = new Route('/user/{user}/message-subscribe/{flag}');
    $flag_tab->setRequirement('_custom_access', '\Drupal\message_subscribe_ui\Controller\SubscriptionController::tabAccess');
    $collection->add('message_subscribe_ui.tab.flag', $flag_tab);

    return $collection;
  }

  /**
   * Both routes end up permission-gated with the custom access check gone.
   *
   * @dataProvider routeNameProvider
   */
  public function testRouteRequiresPermission(string $routeName): void {
    $collection = $this->messageSubscribeRouteCollection();

    $subscriber = new RouteSubscriber();
    $alterRoutes = new \ReflectionMethod($subscriber, 'alterRoutes');
    $alterRoutes->invoke($subscriber, $collection);

    $route = $collection->get($routeName);
    $this->assertSame('administer message subscribe', $route->getRequirement('_permission'));
    $this->assertNull($route->getRequirement('_custom_access'));
  }

  /**
   * Both routes are marked as admin routes, so Gin renders them.
   *
   * @dataProvider routeNameProvider
   */
  public function testRouteUsesAdminTheme(string $routeName): void {
    $collection = $this->messageSubscribeRouteCollection();

    $subscriber = new RouteSubscriber();
    $alterRoutes = new \ReflectionMethod($subscriber, 'alterRoutes');
    $alterRoutes->invoke($subscriber, $collection);

    $route = $collection->get($routeName);
    $this->assertTrue($route->getOption('_admin_route'));
  }

  public static function routeNameProvider(): array {
    return [
      'subscriptions tab' => ['message_subscribe_ui.tab'],
      'per-flag subscriptions tab' => ['message_subscribe_ui.tab.flag'],
    ];
  }

  /**
   * A missing route collection (module not installed) is a no-op.
   */
  public function testMissingRoutesAreNoOp(): void {
    $collection = new RouteCollection();

    $subscriber = new RouteSubscriber();
    $alterRoutes = new \ReflectionMethod($subscriber, 'alterRoutes');
    $alterRoutes->invoke($subscriber, $collection);

    $this->assertNull($collection->get('message_subscribe_ui.tab'));
    $this->assertNull($collection->get('message_subscribe_ui.tab.flag'));
  }

}
