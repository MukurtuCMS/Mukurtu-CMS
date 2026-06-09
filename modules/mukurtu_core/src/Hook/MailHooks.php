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
   * Gates registration email sending on the active combination of site
   * settings so that only the emails listed in the registration workflow
   * matrix are dispatched:
   *
   * - register_no_approval_required: only when email verification is on
   *   (the email carries the one-time login link for first access).
   * - register_pending_approval: only when email verification is on.
   * - register_admin_created: only when the new account is active.
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

    $suppress = match ($message['key']) {
      // Only meaningful when email verification is on: the email carries the
      // one-time login link the visitor needs for their first login.
      'register_no_approval_required' => !$verify_mail,
      // Same reasoning: without email verification there is no OTL to deliver.
      'register_pending_approval' => !$verify_mail,
      // Admin-created emails only go to active accounts; pending and blocked
      // accounts receive no email at creation time.
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
