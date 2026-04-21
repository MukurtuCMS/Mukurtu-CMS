<?php

namespace Drupal\message_subscribe\Subscribers;

/**
 * A delivery candidate implementation.
 */
class DeliveryCandidate implements DeliveryCandidateInterface {

  /**
   * An array of flag IDs that triggered the notification.
   *
   * @var string[]
   */
  protected $flags = [];

  /**
   * An array of notifier IDs for delivery.
   *
   * @var string[]
   */
  protected $notifiers = [];

  /**
   * The delivery candidate account ID.
   *
   * @var int
   */
  protected $uid;

  /**
   * Constructs the delivery candidate.
   *
   * @param string[] $flags
   *   An array of flag IDs.
   * @param string[] $notifiers
   *   An array of notifier IDs.
   * @param int $uid
   *   The delivery candidate account ID.
   */
  public function __construct(array $flags, array $notifiers, $uid) {
    $this->flags = array_combine($flags, $flags);
    $this->notifiers = array_combine($notifiers, $notifiers);
    $this->uid = $uid;
  }

  /**
   * {@inheritdoc}
   */
  public function addFlag($flag_id) {
    $this->flags[$flag_id] = $flag_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeFlag($flag_id) {
    unset($this->flags[$flag_id]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addNotifier($notifier_id) {
    $this->notifiers[$notifier_id] = $notifier_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeNotifier($notifier_id) {
    unset($this->notifiers[$notifier_id]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFlags() {
    return array_unique($this->flags);
  }

  /**
   * {@inheritdoc}
   */
  public function setFlags(array $flag_ids) {
    $this->flags = array_combine($flag_ids, $flag_ids);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getNotifiers() {
    return array_unique($this->notifiers);
  }

  /**
   * {@inheritdoc}
   */
  public function setNotifiers(array $notifier_ids) {
    $this->notifiers = array_combine($notifier_ids, $notifier_ids);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccountId() {
    return $this->uid;
  }

  /**
   * {@inheritdoc}
   */
  public function setAccountId($uid) {
    $this->uid = $uid;
    return $this;
  }

}
