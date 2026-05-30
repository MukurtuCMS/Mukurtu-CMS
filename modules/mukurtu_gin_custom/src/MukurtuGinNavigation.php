<?php

namespace Drupal\mukurtu_gin_custom;

use Drupal\Core\Url;
use Drupal\gin\GinNavigation;

/**
 * Overrides Gin's navigation to use Mukurtu's media view.
 *
 * Gin hardcodes view.media.media_page_list (core) for the "Media" sidebar
 * link, requiring the 'access media overview' Drupal permission. Mukurtu
 * replaces that view with view.mukurtu_media.media_page_list, controlled via
 * _mukurtu_role. This override redirects the check to Mukurtu's route and
 * gracefully handles the case where the core view has been disabled.
 */
class MukurtuGinNavigation extends GinNavigation {

  /**
   * {@inheritdoc}
   *
   * Wraps the parent with exception handling so a missing route (e.g. the core
   * views.view.media disabled by mukurtu_media_install) returns FALSE instead
   * of crashing the navigation build.
   */
  public function hasLinkAccessPermission($route_name, ?array $route_parameters = []) {
    try {
      return parent::hasLinkAccessPermission($route_name, $route_parameters);
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   *
   * Replaces the "Media" entry with a link to Mukurtu's media view.
   */
  public function getNavigationContentMenuItems(): array {
    $items = parent::getNavigationContentMenuItems();

    if (!$this->entityTypeManager->hasDefinition('media')) {
      return $items;
    }

    // Remove any entry the parent may have added for the core media view.
    unset($items['#items']['media']);

    // Add the Mukurtu media overview link when the current user can access it.
    $route = 'view.mukurtu_media.media_page_list';
    try {
      if ($this->hasLinkAccessPermission($route)) {
        $items['#items']['media'] = [
          'title' => $this->t('Media'),
          'class' => 'media',
          'url' => Url::fromRoute($route)->toString(),
        ];
      }
    }
    catch (\Exception $e) {
      // Route unavailable; omit the media item.
    }

    return $items;
  }

}
