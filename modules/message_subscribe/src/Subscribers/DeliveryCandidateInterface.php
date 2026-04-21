<?php

namespace Drupal\message_subscribe\Subscribers;

/**
 * Defines a subscription delivery candidate interface.
 */
interface DeliveryCandidateInterface {

  /**
   * Get the flags that triggered the subscription.
   *
   * @return string[]
   *   An array of subscription flag IDs that triggered the notification.
   */
  public function getFlags();

  /**
   * Sets the flags.
   *
   * @param array $flag_ids
   *   An array of flag IDs.
   *
   * @return static
   *   Return the object.
   */
  public function setFlags(array $flag_ids);

  /**
   * Adds a flag.
   *
   * @param string $flag_id
   *   The flag ID to add.
   *
   * @return static
   *   Return the object.
   */
  public function addFlag($flag_id);

  /**
   * Remove a flag.
   *
   * @param string $flag_id
   *   The flag ID to remove.
   *
   * @return static
   *   Return the object.
   */
  public function removeFlag($flag_id);

  /**
   * Get the notifier IDs.
   *
   * @return string[]
   *   An array of message notifier plugin IDs.
   */
  public function getNotifiers();

  /**
   * Sets the notifier IDs.
   *
   * @param string[] $notifier_ids
   *   An array of notifier IDs.
   *
   * @return static
   *   Return the object.
   */
  public function setNotifiers(array $notifier_ids);

  /**
   * Adds a notifier.
   *
   * @param string $notifier_id
   *   The notifier ID to add.
   *
   * @return static
   *   Return the object.
   */
  public function addNotifier($notifier_id);

  /**
   * Remove a notifier.
   *
   * @param string $notifier_id
   *   The notifier ID to remove.
   *
   * @return static
   *   Return the object.
   */
  public function removeNotifier($notifier_id);

  /**
   * Gets the account ID of the recipient.
   *
   * @return int
   *   The user ID for the delivery.
   */
  public function getAccountId();

  /**
   * Sets the account ID.
   *
   * @param int $uid
   *   The account ID of the delivery candidate.
   *
   * @return static
   *   Return the object.
   */
  public function setAccountId($uid);

}
