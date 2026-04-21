<?php

namespace Drupal\message_notify_test\Plugin\Notifier;

use Drupal\message_notify\Plugin\Notifier\MessageNotifierBase;

/**
 * Test notifier.
 *
 * @Notifier(
 *   id = "test",
 *   title = @Translation("Test notifier"),
 *   description = @Translation("A notifier plugin for tests"),
 *   viewModes = {
 *     "foo",
 *     "bar"
 *   }
 * )
 */
class MessageNotifierTest extends MessageNotifierBase {

  /**
   * {@inheritdoc}
   */
  public function deliver(array $output = []) {
    $this->message->output = $output;

    // Return TRUE or FALSE as it was set on the Message.
    return empty($this->message->fail);
  }

}
