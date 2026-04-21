<?php

namespace Drupal\message_ui;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\message\Entity\Message;

/**
 * Defines an interface for Message UI views contextual links plugins.
 */
interface MessageUiViewsContextualLinksInterface extends PluginInspectionInterface {

  /**
   * Set the message object.
   *
   * @param \Drupal\message\Entity\Message $message
   *   The message object.
   *
   * @return \Drupal\message_ui\MessageUiViewsContextualLinksInterface
   *   The current object.
   */
  public function setMessage(Message $message);

  /**
   * Get te message object.
   *
   * @return \Drupal\message\Entity\Message
   *   The message object.
   */
  public function getMessage();

  /**
   * Return the an array with the router ID and message info.
   *
   * @return array
   *   Array contains the title and the URL.
   */
  public function getRouterInfo();

  /**
   * Checking if the user have access to do the action.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access();

}
