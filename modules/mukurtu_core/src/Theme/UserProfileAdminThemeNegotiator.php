<?php

namespace Drupal\mukurtu_core\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Uses the admin theme when viewing another user's profile page.
 *
 * Only managers/admins ever reach another user's profile (self-view is
 * always separately allowed by core), so treat that case as an admin action
 * rather than leaving it on the unstyled default theme.
 */
class UserProfileAdminThemeNegotiator implements ThemeNegotiatorInterface {

  public function __construct(protected AccountInterface $currentUser, protected ConfigFactoryInterface $configFactory) {
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    if ($route_match->getRouteName() !== 'entity.user.canonical') {
      return FALSE;
    }

    $user = $route_match->getParameter('user');
    $uid = is_object($user) ? $user->id() : $user;
    return $uid !== NULL && (int) $uid !== (int) $this->currentUser->id();
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    return $this->configFactory->get('system.theme')->get('admin');
  }

}
