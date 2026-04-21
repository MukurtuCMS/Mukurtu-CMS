<?php

namespace Drupal\message_subscribe;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;

/**
 * Subscribers service.
 */
interface SubscribersInterface {

  /**
   * Process a message and send to subscribed users.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object to process subscriptions and send notifications for.
   * @param \Drupal\message\MessageInterface $message
   *   The message object.
   * @param array $notify_options
   *   (optional) An array of options to be passed to the message notifier
   *   service. See `\Drupal\message_notify\MessageNotifier::send()`.
   * @param array $subscribe_options
   *   (optional) Array with the following optional values:
   *   - 'save message' (defaults to TRUE) Determine if the Message should be
   *     saved.
   *   - 'skip context' (defaults to FALSE) determine if extracting basic
   *      context should be skipped in `self::getSubscribers()`.
   *   - 'last uid' (defaults to 0) Only query UIDs greater than this UID.
   *   - 'uids': Array of user IDs to be processed. Setting this, will cause
   *     skipping `self::getSubscribers()` to get the subscribed
   *     users.
   *   - 'range': (defaults to FALSE) limit the number of items to fetch in the
   *     subscribers query.
   *   - 'end time': The timestamp of the time limit for the function to
   *     execute. Defaults to FALSE, meaning there is no time limitation.
   *   - 'use queue': Determine if queue API should be used to
   *   - 'queue': Set to TRUE to indicate the processing is done via a queue
   *     worker.
   *   - 'entity access: (defaults to TRUE) determine if access to view the
   *     entity should be applied when getting the list of subscribed users.
   *   - 'notify blocked users' (defaults to the global setting in
   *     `message_subscribe.settings`) determine whether blocked users
   *      should be notified. Typically this should be used in conjunction with
   *      'entity access' to ensure that blocked users don't receive
   *      notifications about entities which they used to have access to
   *      before they were blocked.
   *   - 'notify message owner' (defaults to the global setting in
   *      `message_subscribe.settings`) determines if the user that created the
   *      entity gets notified of their own action. If TRUE the author will get
   *      notified.
   * @param array $context
   *   (optional) array keyed with the entity type and array of entity IDs as
   *   the value. For example, if the event is related to a node
   *   entity, the implementing module might pass along with the node
   *   itself, the node author and related taxonomy terms.
   *
   *   Example usage.
   *
   * @code
   *   $subscribe_options['uids'] = [
   *     1 => [
   *       'notifiers' => ['email'],
   *     ],
   *   ];
   *   $context = [
   *     'node' => [1],
   *     // The node author.
   *     'user' => [10],
   *     // Related taxonomy terms.
   *     'taxonomy_term' => [100, 200, 300]
   *   ];
   * @endcode
   */
  public function sendMessage(EntityInterface $entity, MessageInterface $message, array $notify_options = [], array $subscribe_options = [], array $context = []);

  /**
   * Retrieve a list of subscribers for a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to retrieve subscribers for.
   * @param \Drupal\message\MessageInterface $message
   *   The message entity.
   * @param array $options
   *   (optional) An array of options with the same elements as the
   *   `$subscribe_options` array for `self::sendMessage()`.
   * @param array $context
   *   (optional) The context array, passed by reference. This has the same
   *   elements as the `$context` parameter for `self::sendMessage()`.
   *
   * @return \Drupal\message_subscribe\Subscribers\DeliveryCandidateInterface[]
   *   Array of delivery candidate objects keyed with the user IDs to send
   *   notifications to.
   */
  public function getSubscribers(EntityInterface $entity, MessageInterface $message, array $options = [], array &$context = []);

  /**
   * Get context from a given entity type.
   *
   * This is a naive implementation, which extracts context from an entity.
   * For example, given a node we extract the node author and related
   * taxonomy terms.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   * @param bool $skip_detailed_context
   *   (optional) Skip detailed context detection and just use entity ID/type.
   *   Defaults to FALSE.
   * @param array $context
   *   (optional) The starting context array to modify.
   *
   * @return array
   *   Array keyed with the entity type and array of entity IDs as the value.
   */
  public function getBasicContext(EntityInterface $entity, $skip_detailed_context = FALSE, array $context = []);

  /**
   * Get Message subscribe related flags.
   *
   * Return Flags related to message subscribe using a name convention --
   * the flag name should start with "subscribe_".
   *
   * @param string $entity_type
   *   (optional) The entity type for which to load the flags.
   * @param string $bundle
   *   (optional) The bundle for which to load the flags.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional) The user account to filter available flags. If not set, all
   *   flags for the given entity and bundle will be returned.
   *
   * @return \Drupal\flag\FlagInterface[]
   *   An array of the structure [fid] = flag_object.
   *
   * @see \Drupal\flag\FlagServiceInterface::getAllFlags()
   */
  public function getFlags($entity_type = NULL, $bundle = NULL, ?AccountInterface $account = NULL);

}
