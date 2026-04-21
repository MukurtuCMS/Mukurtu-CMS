<?php

namespace Drupal\message_digest\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines an interface for message digest interval configs.
 */
interface MessageDigestIntervalInterface extends ConfigEntityInterface {

  /**
   * Gets the message digest interval.
   *
   * @return string
   *   The message digest interval.
   */
  public function getInterval();

  /**
   * Gets the interval description.
   *
   * @return string
   *   The message digest description.
   */
  public function getDescription();

}
