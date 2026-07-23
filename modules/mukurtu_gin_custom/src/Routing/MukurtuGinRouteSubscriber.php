<?php

namespace Drupal\mukurtu_gin_custom\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Marks Layout Builder off-canvas routes as admin routes.
 *
 * Ensures the Gin admin theme is used when these routes are opened in a
 * dialog (e.g. via the front-end block edit feature). Every off-canvas step
 * needs to be included here, not just update_block/add_block: Layout
 * Builder's "add a new block" flow is choose_block (or choose_inline_block)
 * followed by add_block, all within the same dialog session. If only some
 * of those routes are marked as admin routes, the active theme changes
 * mid-session, and Drupal's per-theme asset-loaded tracking doesn't
 * recognize shared core JS (e.g. core/misc/dialog/dialog.js) as already
 * loaded under the new theme - causing it to be fetched and executed again,
 * which throws on the resulting class redeclaration and aborts whatever
 * else was in that script bundle (observed as "ajax.$form.ajaxSubmit is not
 * a function" when saving a newly added inline block).
 */
class MukurtuGinRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $route_names = [
      'layout_builder.choose_section',
      'layout_builder.add_section',
      'layout_builder.configure_section',
      'layout_builder.remove_section',
      'layout_builder.choose_block',
      'layout_builder.add_block',
      'layout_builder.choose_inline_block',
      'layout_builder.update_block',
      'layout_builder.move_block_form',
      'layout_builder.remove_block',
    ];
    foreach ($route_names as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->setOption('_admin_route', TRUE);
      }
    }
  }

}
