<?php

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for mukurtu_core theme negotiation.
 */
class ThemeHooks {

  /**
   * Permissions that let a user reach some Layout Builder route.
   *
   * Kept in sync with the bundle-specific override permissions this
   * codebase already hardcodes elsewhere
   * (@see LayoutBuilderHooks::restrictBlocksForRole()) and the
   * layout_builder__layout field on the page/landing_page bundles. Add a
   * bundle's override permission here if it gains Layout Builder overrides
   * in the future.
   */
  private const LAYOUT_BUILDER_PERMISSIONS = [
    'configure any layout',
    'configure editable page node layout overrides',
    'configure editable landing_page node layout overrides',
  ];

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

  /**
   * Implements hook_page_attachments().
   *
   * Gin's gin_preprocess_breadcrumb() points the "Back to site" link at the
   * current route's entity canonical URL when accessible. On
   * entity.user.canonical that URL is this page itself, so when
   * UserProfileAdminThemeNegotiator forces the admin theme there, the link
   * becomes a self-referential no-op instead of returning anywhere useful.
   * Attach a behavior that corrects the href client-side for exactly that
   * case; see js/user-profile-back-to-site-link.js.
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    /** @var \Drupal\mukurtu_core\Theme\UserProfileAdminThemeNegotiator $negotiator */
    $negotiator = \Drupal::service('mukurtu_core.user_profile_admin_theme_negotiator');
    if ($negotiator->applies(\Drupal::routeMatch())) {
      $attachments['#attached']['library'][] = 'mukurtu_core/user-profile-back-to-site-link';
    }
  }

  /**
   * Implements hook_page_attachments_alter().
   *
   * Entity browser modals (mukurtu_content_browser, etc.) render as a
   * separate full page inside an iframe, so Klaro's hook_page_attachments()
   * attaches its floating "Manage consents" toggle button there too,
   * duplicating the one already on the parent page. Suppress just the
   * button for these routes; leave Klaro's consent enforcement itself
   * untouched, since browser results can still surface consent-gated
   * external content.
   *
   * Also drops the "Layout Builder notifications" (toastify) service from
   * Klaro's list for visitors who could never reach Layout Builder. That
   * service is registered as required so Klaro never blocks it for the
   * people who do use Layout Builder, but Klaro has no concept of per-user
   * visibility, so without this every visitor's consent modal shows it.
   */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments): void {
    $route_name = \Drupal::routeMatch()->getRouteName();
    if ($route_name !== NULL && str_starts_with($route_name, 'entity_browser.') && isset($attachments['#attached']['drupalSettings']['klaro'])) {
      $attachments['#attached']['drupalSettings']['klaro']['show_toggle_button'] = FALSE;
    }

    $services = $attachments['#attached']['drupalSettings']['klaro']['config']['services'] ?? NULL;
    if (is_array($services)) {
      $attachments['#cache']['contexts'][] = 'user.permissions';
      if (!$this->currentUserCanUseLayoutBuilder()) {
        $attachments['#attached']['drupalSettings']['klaro']['config']['services'] = array_values(
          array_filter($services, static fn (array $service): bool => ($service['name'] ?? NULL) !== 'toastify')
        );
      }
    }
  }

  /**
   * Whether the current user has any permission that reaches Layout Builder.
   */
  private function currentUserCanUseLayoutBuilder(): bool {
    $account = \Drupal::currentUser();
    foreach (self::LAYOUT_BUILDER_PERMISSIONS as $permission) {
      if ($account->hasPermission($permission)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
