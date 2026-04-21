<?php

namespace Drupal\message_notify\Plugin\Notifier;

use Drupal\message_notify\Exception\MessageNotifyException;

/**
 * SMS notifier.
 *
 * @todo Add plugin definition.
 */
class Sms extends MessageNotifierBase {

  /**
   * {@inheritdoc}
   */
  public function deliver(array $output = []) {
    if (TRUE) {
      throw new MessageNotifyException('This functionality depends on the SMS Framework module. See: https://www.drupal.org/node/2582937');
    }
    if (empty($this->message->smsNumber)) {
      // Try to get the SMS number from the account.
      $account = $this->message->uid->entity;
      if (!empty($account->sms_user['number'])) {
        $this->message->smsNumber = $account->sms_user['number'];
      }
    }

    if (empty($this->message->smsNumber)) {
      throw new MessageNotifyException('Message cannot be sent using SMS as the "smsNumber" property is missing from the Message entity or user entity.');
    }

    return sms_send($this->message->smsNumber, strip_tags($output['message_notify_sms_body']));
  }

}
