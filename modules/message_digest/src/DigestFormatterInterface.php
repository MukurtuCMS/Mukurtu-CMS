<?php

namespace Drupal\message_digest;

use Drupal\user\UserInterface;

/**
 * An interface for formatting message digests.
 */
interface DigestFormatterInterface {

  /**
   * Build the full message digest content for a given set of messages.
   *
   * @param \Drupal\message\MessageInterface[] $digest
   *   An array of messages.
   * @param array $view_modes
   *   An array of view modes to build.
   * @param \Drupal\user\UserInterface $recipient
   *   The digest recipient.
   *
   * @return string
   *   Fully rendered message digest.
   */
  public function format(array $digest, array $view_modes, UserInterface $recipient);

}
