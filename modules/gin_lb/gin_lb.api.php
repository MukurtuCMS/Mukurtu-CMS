<?php

/**
 * @file
 * Hooks for the gin_lb module.
 */

declare(strict_types=1);

/**
 * @addtogroup hooks
 *
 * @{
 */

/**
 * Alter if the gin lb toolbar is shown.
 *
 * @param bool $gin_lb_show_toolbar
 *   Alter this boolean flag.
 */
function hook_gin_lb_show_toolbar_alter(&$gin_lb_show_toolbar) {
  // Sample check if layout builder is used inside page manager.
  $route_match = \Drupal::routeMatch();
  $route_name = $route_match->getRouteName();
  if ($route_name === 'entity.page.add_step_form' && $route_match->getParameter('step') === 'layout_builder') {
    $gin_lb_show_toolbar = FALSE;
  }
}

/**
 * Helps to detect if a route is a layout builder route.
 *
 * @param bool $gin_lb_is_layout_builder_route
 *   Boolean flag.
 */
function hook_gin_lb_is_layout_builder_route_alter(&$gin_lb_is_layout_builder_route) {
  $route_match = \Drupal::routeMatch();
  $route_name = $route_match->getRouteName();
  if ($route_name === 'entity.page.add_step_form' && $route_match->getParameter('step') === 'layout_builder') {
    $gin_lb_is_layout_builder_route = TRUE;
  }
}

/**
 * @} End of "addtogroup hooks".
 */
