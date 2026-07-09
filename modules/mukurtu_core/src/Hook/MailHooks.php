<?php

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

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
   * - register_pending_approval: always sent for visitor self-registration.
   *   This email contains no OTL — it just notifies the visitor their account
   *   is awaiting approval. Suppress only for admin-created accounts.
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
   * - register_pending_approval_admin: sent using core's own
   *   /admin/config/people/accounts subject/body config, but widened to
   *   reach every user with the 'administer users' permission instead of
   *   just the single site notification address. This keeps the email's
   *   content editable in one place instead of two.
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
      // Pending-approval notification: no OTL, just tells the visitor their
      // account is awaiting review. Always send for visitor self-registration;
      // suppress only when an admin is creating the account.
      'register_pending_approval' => $isAdminCreated,
      // Admin-created welcome email: only for active accounts. Pending and
      // blocked accounts receive no email at creation time.
      'register_admin_created' => $account && !$account->isActive(),
      default => FALSE,
    };

    if ($suppress) {
      $message['send'] = FALSE;
      return;
    }

    if ($message['key'] === 'register_pending_approval_admin') {
      $this->widenAdminNotificationRecipients($message);
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

  /**
   * Redirects the pending-approval admin notification to all admins.
   *
   * Core only sends this to the single configured site notification
   * address. Mukurtu wants everyone with the 'administer users' permission
   * notified, so the 'to' address is widened to a comma-separated list
   * (the mail backend's To header accepts multiple addresses this way)
   * rather than replacing the mail entirely with a separate template.
   */
  private function widenAdminNotificationRecipients(array &$message): void {
    $rids = [];
    foreach (Role::loadMultiple() as $role) {
      if ($role->hasPermission('administer users')) {
        $rids[] = $role->id();
      }
    }
    if (empty($rids)) {
      return;
    }

    $uids = \Drupal::entityTypeManager()->getStorage('user')->getQuery()
      ->accessCheck(FALSE)
      ->condition('roles', $rids, 'IN')
      ->execute();
    if (empty($uids)) {
      return;
    }

    $email_validator = \Drupal::service('email.validator');
    $emails = [];
    foreach (User::loadMultiple($uids) as $admin_user) {
      if ($email_validator->isValid($admin_user->getEmail())) {
        $emails[] = $admin_user->getEmail();
      }
    }
    if (!empty($emails)) {
      $message['to'] = implode(',', $emails);
    }
  }

}
