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
   * Prevents a fatal TypeError in PhpMail when a user account has no email
   * address and a status-change notification is triggered (e.g. unblock).
   */
  #[Hook('mail_alter')]
  public function mailAlter(array &$message): void {
    if (empty($message['to']) && $message['module'] === 'user') {
      $message['send'] = FALSE;
      \Drupal::logger('mukurtu_core')->warning(
        'Suppressed @key notification for user @uid: account has no email address.',
        ['@key' => $message['key'], '@uid' => $message['params']['account']?->id() ?? 'unknown']
      );
    }
  }

}
