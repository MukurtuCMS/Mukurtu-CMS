<?php

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for mukurtu_core mail handling.
 */
class MailHooks {

  /**
   * Implements hook_mail_alter().
   *
   * Gates registration email sending so that only the emails defined in the
   * registration workflow matrix are dispatched:
   *
   * Self-registration:
   * - register_no_approval_required: only when email verification is on
   *   (the email carries the one-time login link for first access).
   * - register_pending_approval: only when email verification is on.
   *
   * Admin-created accounts:
   * - register_admin_created: only when the new account is active AND the
   *   admin checked "Notify user". All other admin-created combinations
   *   (pending, blocked, or notify unchecked) send no email.
   * - register_no_approval_required / register_pending_approval: suppressed;
   *   Drupal core can emit these as a fallback for admin-created accounts but
   *   they are never appropriate here.
   *
   * Site-admin notification:
   * - register_pending_approval_admin: always suppressed. Mukurtu uses its
   *   own community-manager notification system instead.
   *
   * Also prevents a fatal TypeError in PhpMail when a user account has no
   * email address and a status-change notification is triggered (e.g. unblock).
   */
  #[Hook('mail_alter')]
  public function mailAlter(array &$message): void {
    if ($message['module'] !== 'user') {
      return;
    }

    $account = $message['params']['account'] ?? NULL;
    $verify_mail = \Drupal::config('user.settings')->get('verify_mail');
    // An authenticated current user means an admin is creating the account,
    // not a visitor self-registering.
    $isAdminCreated = \Drupal::currentUser()->isAuthenticated();

    $suppress = match ($message['key']) {
      // Self-registration only: carries the OTL for first login. Suppress
      // when verify is off (user is auto-logged in) or when an admin is
      // creating the account (core can emit this as a fallback).
      'register_no_approval_required' => !$verify_mail || $isAdminCreated,
      // Same reasoning as above.
      'register_pending_approval' => !$verify_mail || $isAdminCreated,
      // Site-admin "Account details" notification — never needed; Mukurtu
      // uses its own community-manager notification workflow.
      'register_pending_approval_admin' => TRUE,
      // Admin-created welcome email: only for active accounts. Pending and
      // blocked accounts receive no email at creation time.
      'register_admin_created' => $account && !$account->isActive(),
      default => FALSE,
    };

    if ($suppress) {
      $message['send'] = FALSE;
      return;
    }

    // Prevent a fatal TypeError when the account has no email address.
    if (empty($message['to'])) {
      $message['send'] = FALSE;
      \Drupal::logger('mukurtu_core')->warning(
        'Suppressed @key notification for user @uid: account has no email address.',
        ['@key' => $message['key'], '@uid' => $account?->id() ?? 'unknown']
      );
    }
  }

}
