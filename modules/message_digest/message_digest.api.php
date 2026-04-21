<?php

/**
 * @file
 * Hooks provided by the Message Digest module.
 */

use Drupal\message_notify\Plugin\Notifier\MessageNotifierInterface;
use Drupal\user\UserInterface;

/**
 * Allow modules to messages being aggregated for digests.
 *
 * @param array $context
 *   An array consisting of the following data:
 *   - 'data': The raw result row from the `message_digest` table.
 *   - 'entity_type': The entity type. Set to an empty string to switch to a
 *     global digest (eg, un-grouped).
 *   - 'entity_id': The entity ID. Set to an empty string to switch to a global
 *     digest (eg, un-grouped).
 *   - 'messages': An array of messages to be sent. These are in the order
 *     they'll appear in the digest, so can be re-arranged/sorted as needed.
 * @param \Drupal\user\UserInterface $account
 *   The user account object the digests are being gathered for.
 * @param \Drupal\message_notify\Plugin\Notifier\MessageNotifierInterface $notifier
 *   The notifier plugin being used.
 */
function hook_message_digest_aggregate_alter(array &$context, UserInterface $account, MessageNotifierInterface $notifier) {
  // Aggregate all content into one digest by setting gid to 0 for users that
  // have opted in via a field called `aggregate_content`, and if this is the
  // weekly digest.
  if ($account->aggregate_content->value && $notifier->getPluginId() === 'message_digest:weekly') {
    $context['gid'] = 0;
  }
}

/**
 * Allow modules to change view modes prior to rendering, or stop delivery.
 *
 * @param array $context
 *   An array consisting of the following data:
 *   - 'view_modes': An array of view mode names that will be used to render
 *     the messages. Add or remove to change what information is rendered.
 *   - 'deliver': A boolean indicating if this digest should be delivered. It
 *     defaults to TRUE. Set to FALSE to stop delivery. The digest will still
 *     be marked as being sent.
 *   - 'entity_type': The entity type. This will be an empty string for global,
 *     non-grouped digests.
 *   - 'entity_id': The entity ID. This will be an empty string for global, non-
 *     grouped digests.
 *   - 'messages: An array of message IDs that are being assembled for the
 *     digest.
 * @param \Drupal\message_notify\Plugin\Notifier\MessageNotifierInterface $notifier
 *   The message notifier being used.
 * @param \Drupal\user\UserInterface $account
 *   The recipient of the digest.
 */
function hook_message_digest_view_mode_alter(array &$context, MessageNotifierInterface $notifier, UserInterface $account) {
  // Remove the email subject view mode.
  if (isset($context['view_modes']['mail_subject'])) {
    unset($context['view_modes']['mail_subject']);
  }

  // If the account is blocked, do not deliver.
  if ($account->isBlocked()) {
    $context['deliver'] = FALSE;
  }
}
