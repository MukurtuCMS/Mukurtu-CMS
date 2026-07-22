<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles dismissal of the UID 1 super admin warning message.
 */
class SuperAdminWarningController extends ControllerBase {

  const SESSION_KEY = 'mukurtu_core_super_admin_warning_dismissed';

  /**
   * Marks the super admin warning as dismissed for the current session.
   */
  public function dismiss(Request $request): RedirectResponse {
    $request->getSession()->set(self::SESSION_KEY, TRUE);
    return $this->redirect('<front>');
  }

  /**
   * Restricts the dismiss route to UID 1, who is the only one who ever sees
   * the link.
   */
  public function access(AccountInterface $account): AccessResult {
    return AccessResult::allowedIf((int) $account->id() === 1);
  }

}
