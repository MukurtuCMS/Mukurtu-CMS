<?php

/**
 * @file
 * Hooks provided by the Message subscribe module.
 */

/**
 * @addtogroup hooks
 * @{
 */

use Drupal\message\MessageInterface;
use Drupal\message_subscribe\Subscribers\DeliveryCandidate;
use Drupal\message_subscribe\Subscribers\DeliveryCandidateInterface;

/**
 * Allow modules to add user IDs that need to be notified.
 *
 * @param \Drupal\message\MessageInterface $message
 *   The message object.
 * @param array $subscribe_options
 *   Subscription options as defined by
 *   \Drupal\message\MessageInterface::sendMessage().
 * @param array $context
 *   Array keyed with the entity type and array of entity IDs as the
 *   value. According to this context this function will retrieve the
 *   related subscribers.
 *
 * @return \Drupal\message_subscribe\Subscribers\DeliveryCandidateInterface[]
 *   An array, keyed by recipient user ID, of delivery candidate objects.
 */
function hook_message_subscribe_get_subscribers(MessageInterface $message, array $subscribe_options = [], array $context = []) {
  return [
    2 => new DeliveryCandidate(['subscribe_node'], ['sms'], 2),
    7 => new DeliveryCandidate(['subscribe_og', 'subscribe_user'], [
      'sms',
      'email',
    ], 7),
  ];
}

/**
 * Alter the subscribers list.
 *
 * @param \Drupal\message_subscribe\Subscribers\DeliveryCandidateInterface[] &$uids
 *   The array of delivery candidates as defined by
 *   `hook_message_subscribe_get_subscribers()`.
 * @param array $values
 *   A keyed array of values containing:
 *   - 'context' - The context array.
 *   - 'entity_type' - The entity type ID.
 *   - 'entity' - The entity object
 *   - 'subscribe_options' - The subscribe options array.
 */
function hook_message_subscribe_get_subscribers_alter(array &$uids, array $values) {

}

/**
 * Alter the message entity immediately before it is sent.
 *
 * @param \Drupal\message\MessageInterface $message
 *   The message entity to be sent. This already has the recipient set as the
 *   message owner.
 * @param \Drupal\message_subscribe\Subscribers\DeliveryCandidateInterface $delivery_candidate
 *   A delivery candidate object.
 */
function hook_message_subscribe_message_alter(MessageInterface $message, DeliveryCandidateInterface $delivery_candidate) {

}

/**
 * @} End of "addtogroup hooks".
 */
