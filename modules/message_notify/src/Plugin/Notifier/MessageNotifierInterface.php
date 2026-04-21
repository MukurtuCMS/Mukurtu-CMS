<?php

namespace Drupal\message_notify\Plugin\Notifier;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\message\MessageInterface;

/**
 * Additional behaviors for a Entity Reference field.
 *
 * Implementations that wish to provide an implementation of this should
 * register it using CTools' plugin system.
 */
interface MessageNotifierInterface extends ContainerFactoryPluginInterface, PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Entry point to send and process a message.
   *
   * @return bool
   *   TRUE or FALSE based on delivery status.
   */
  public function send();

  /**
   * Deliver a message via the required transport method.
   *
   * @param array $output
   *   Array keyed by the view mode, and the rendered entity in the
   *   specified view mode.
   *
   * @return bool
   *   TRUE or FALSE based on delivery status.
   */
  public function deliver(array $output = []);

  /**
   * Act upon send result.
   *
   * @param bool $result
   *   The result from delivery.
   * @param array $output
   *   The message output array.
   */
  public function postSend($result, array $output = []);

  /**
   * Determine if user can access notifier.
   */
  public function access();

  /**
   * Set the message object for the notifier.
   *
   * @param \Drupal\message\MessageInterface $message
   *   The message object.
   */
  public function setMessage(MessageInterface $message);

}
