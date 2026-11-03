<?php

/**
 * @file
 * Post update functions for the mukurtu_protocol module.
 */

/**
 * Make cached lists of communities, protocols, and media vary by membership.
 */
function mukurtu_protocol_post_update_listing_cache_varies_by_membership(): void {
  // Views stores each display's cache contexts in config when the view is
  // saved. Saving recalculates them, which picks up the mukurtu_memberships
  // list cache context now declared on community, protocol, and media.
  foreach (\Drupal::entityTypeManager()->getStorage('view')->loadMultiple() as $view) {
    $view->save();
  }
}
