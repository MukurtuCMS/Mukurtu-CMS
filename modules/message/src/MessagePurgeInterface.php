<?php

namespace Drupal\message;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for MessagePurge plugins.
 */
interface MessagePurgeInterface extends ConfigurableInterface, PluginFormInterface {

  /**
   * The maximum number of messages that a queue worker should delete at once.
   */
  const MESSAGE_DELETE_SIZE = 100;

  /**
   * Fetch the messages that need to be purged for a given template.
   *
   * @param \Drupal\message\MessageTemplateInterface $template
   *   The message template to fetch messages for.
   *
   * @return array
   *   An array of \Drupal\message\MessageInterface entity IDs.
   */
  public function fetch(MessageTemplateInterface $template);

  /**
   * Process the purge-able messages.
   *
   * Normally this is a bulk delete operation.
   *
   * @param array $ids
   *   The message IDs to be processed.
   */
  public function process(array $ids);

  /**
   * Returns the purge method label.
   *
   * @return string
   *   The purge method label.
   */
  public function label();

  /**
   * Returns the purge method description.
   *
   * @return string
   *   The purge plugin description.
   */
  public function description();

  /**
   * Returns the weight of the purge plugin.
   *
   * @return int
   *   The integer weight of the purge plugin.
   */
  public function getWeight();

  /**
   * Sets the weight for this purge plugin.
   *
   * @param int $weight
   *   The weight for this purge plugin.
   *
   * @return $this
   */
  public function setWeight($weight);

}
