<?php

namespace Drupal\mukurtu_core\Hook;

/**
 * Views hook implementations moved to mukurtu_core.module.
 *
 * hook_views_pre_build and hook_views_query_alter are implemented as
 * procedural hooks because the OOP #[Hook] invocation path for Views hooks
 * was not firing reliably in this Drupal version.
 */
class ViewsHooks {
}
