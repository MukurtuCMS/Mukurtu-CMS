<?php

namespace Drupal\message_digest\Plugin\Notifier;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\user\UserInterface;

/**
 * Interface for sending messages as digests.
 */
interface DigestInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Aggregate all of the messages for this digest.
   *
   * Collect all messages for the digest's interval and notifier that haven't
   * already been sent, and group them by user and then by group.
   *
   * @param int $uid
   *   The recipient's user ID for which to aggregate the digest.
   * @param int $end_time
   *   The unix timestamp from which all messages in the past will be
   *   aggregated.
   *
   * @return array
   *   An array of message IDs for the given user, keyed by entity_type, then by
   *   entity_id.
   *
   * @code
   *   $aggregate = [
   *     '' => [
   *       // Non-grouped message digests here.
   *       12, 24,
   *     ],
   *     'node' => [
   *       23 => [1, 3, 5],
   *     ],
   *   ];
   * @endcode
   */
  public function aggregate($uid, $end_time);

  /**
   * Get a unique list of recipient user IDs for this digest.
   *
   * @return int[]
   *   An array of unique recipients for this digest.
   */
  public function getRecipients();

  /**
   * Mark the sent digest messages as sent in the message_digest DB table.
   *
   * @param \Drupal\user\UserInterface $account
   *   User account for which to mark the digest as sent.
   * @param int $last_mid
   *   The last MID to be sent in the digest.
   */
  public function markSent(UserInterface $account, $last_mid);

  /**
   * Determine if it is time to process this digest or not.
   *
   * @return bool
   *   Returns TRUE if a sufficient amount of time has passed.
   */
  public function processDigest();

  /**
   * Gets the end time for which to digest messages prior to.
   *
   * @return int
   *   The unix timestamp for which all messages prior will be digested.
   */
  public function getEndTime();

  /**
   * Sets the last sent time.
   */
  public function setLastSent();

  /**
   * The interval to compile digests for.
   *
   * @return string
   *   The interval. This should be compatible with `strtotime()`.
   */
  public function getInterval();

}
