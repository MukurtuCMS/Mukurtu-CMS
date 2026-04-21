<?php

namespace Drupal\message_ui;

use Drupal\Component\Plugin\PluginBase;
use Drupal\message\Entity\Message;

/**
 * Base class for Message UI views contextual links plugins.
 */
abstract class MessageUiViewsContextualLinksBase extends PluginBase implements MessageUiViewsContextualLinksInterface {

  /**
   * The message object.
   *
   * @var \Drupal\message\Entity\Message
   */
  protected $message;

  /**
   * {@inheritdoc}
   */
  public function setMessage(Message $message) {
    $this->message = $message;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage() {
    return $this->message;
  }

}
