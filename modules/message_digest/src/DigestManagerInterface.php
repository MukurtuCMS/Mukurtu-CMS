<?php

namespace Drupal\message_digest;

/**
 * Declares an interface for managing message digests.
 */
interface DigestManagerInterface {

  /**
   * Cleanup old messages from the message_digest table.
   *
   * @deprecated in message_digest:8.1.2 and is removed from message_digest:2.0.0.
   *   Entries in the message_digest table are cleaned up automatically when
   *   messages or users are deleted so this is no longer needed.
   *
   * @see https://www.drupal.org/node/2285947
   */
  public function cleanupOldMessages();

  /**
   * Processes digests waiting to be aggregated.
   *
   * These are queued for processing and sending individually.
   */
  public function processDigests();

  /**
   * Processes and sends an individual digest for a given user.
   *
   * @param int $account_id
   *   The recipient's account ID.
   * @param string $notifier_id
   *   The digest notifier plugin ID.
   * @param int $end_time
   *   The unix timestamp prior to which to aggregate digests.
   */
  public function processSingleUserDigest($account_id, $notifier_id, $end_time);

  /**
   * Returns the Digest notifier plugins.
   *
   * @return \Drupal\message_digest\Plugin\Notifier\DigestInterface[]
   *   An associative array of Digest notifier plugins, keyed by plugin ID.
   */
  public function getNotifiers();

}
