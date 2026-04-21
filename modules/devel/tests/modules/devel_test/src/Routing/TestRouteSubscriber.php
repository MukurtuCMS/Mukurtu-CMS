<?php

namespace Drupal\devel_test\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\State\State;
use Symfony\Component\Routing\RouteCollection;

/**
 * Router subscriber class for testing purpose.
 */
class TestRouteSubscriber extends RouteSubscriberBase {

  /**
   * Constructor method.
   *
   * @param \Drupal\Core\State\State $state
   *   The object State.
   */
  public function __construct(protected State $state) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $this->state->set('devel_test_route_rebuild', 'Router rebuild fired');
  }

}
