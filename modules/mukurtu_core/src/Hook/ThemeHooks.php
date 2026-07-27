<?php

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for mukurtu_core theme negotiation.
 */
class ThemeHooks {

  /**
   * Implements hook_js_settings_alter().
   *
   * UserProfileAdminThemeNegotiator forces the admin theme when viewing
   * another user's profile, but the underlying route isn't (and shouldn't
   * be) marked as an admin route, so core's AdminContext::isAdminRoute()
   * still reports FALSE there. The toolbar/Gin "Back to site" link keys off
   * drupalSettings.path.currentPathIsAdmin to decide where it points, so
   * without this it falls back to the front page and clobbers the saved
   * "last non-admin page" used by real admin pages. Force it to match the
   * theme that's actually rendered, for exactly the same condition the
   * negotiator uses.
   */
  #[Hook('js_settings_alter')]
  public function jsSettingsAlter(array &$settings, AttachedAssetsInterface $assets): void {
    /** @var \Drupal\mukurtu_core\Theme\UserProfileAdminThemeNegotiator $negotiator */
    $negotiator = \Drupal::service('mukurtu_core.user_profile_admin_theme_negotiator');
    if ($negotiator->applies(\Drupal::routeMatch())) {
      $settings['path']['currentPathIsAdmin'] = TRUE;
    }
  }

}
