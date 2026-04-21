<?php

namespace Drupal\message_digest\Traits;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\message\MessageInterface;
use Drupal\message_digest\Plugin\Notifier\DigestInterface;

/**
 * Methods useful for testing and integrating the Message Digest module.
 */
trait MessageDigestTrait {

  /**
   * Returns the total number of undelivered digest messages.
   *
   * @return int
   *   The number of undelivered messages.
   */
  protected function countAllUndeliveredDigestMessages() {
    $count = 0;
    foreach ($this->getMessageDigestManager()->getNotifiers() as $notifier) {
      foreach ($notifier->getRecipients() as $uid) {
        $count += count($this->getUserMessagesByNotifier($notifier, $uid));
      }
    }
    return $count;
  }

  /**
   * Makes sure all notifiers will process messages at the next cron run.
   */
  protected function expireMessageDigestNotifiers() {
    /** @var \Drupal\Core\State\StateInterface $state */
    $state = \Drupal::service('state');
    foreach ($this->getMessageDigestManager()->getNotifiers() as $notifier) {
      $key = $notifier->getPluginId() . '_last_run';
      $state->set($key, 0);
    }
  }

  /**
   * Makes sure all digest messages will be sent out at the next cron run.
   */
  protected function expireDigestMessages() {
    // Make sure all unsent messages have a timestamp 1 day in the past. Any
    // messages that are created during the test have a newer timestamp than the
    // request of the bootstrapped Drupal instance, causing them to be ignored
    // during sending.
    /** @var \Drupal\Core\Database\Connection $database */
    $database = \Drupal::service('database');
    $database->update('message_digest')
      ->fields(['timestamp' => time() - 24 * 60 * 60])
      ->condition('sent', 0)
      ->execute();
  }

  /**
   * Returns the message rendered in the given view mode.
   *
   * @param \Drupal\message\MessageInterface $message
   *   The message to render.
   * @param string $view_mode
   *   The view mode to use.
   *
   * @return string
   *   The rendered message.
   */
  protected function getRenderedMessage(MessageInterface $message, $view_mode) {
    $content = \Drupal::entityTypeManager()->getViewBuilder('message')->view($message, $view_mode);
    return (string) \Drupal::service('renderer')->renderPlain($content);
  }

  /**
   * Returns the messages that will be includes in the user's next digest.
   *
   * @param \Drupal\message_digest\Plugin\Notifier\DigestInterface $notifier
   *   The digest notifier that is responsible for sending out the messages.
   * @param int $uid
   *   The ID of the user for which to return the messages.
   * @param string $entity_type
   *   Optional entity type. If supplied along with the $label property this can
   *   be used to return only messages that refer to the given entity.
   * @param string $label
   *   Optional entity label. If supplied along with the $entity_type property
   *   this can be used to return only messages that refer to the given entity.
   *
   * @return \Drupal\message\MessageInterface[]
   *   The messages.
   */
  protected function getUserMessagesByNotifier(DigestInterface $notifier, $uid, $entity_type = NULL, $label = NULL) {
    // We can't use `$notifier->getEndTime()` since the Behat request has been
    // started earlier than the Drupal requests that created the digests.
    $digests = $notifier->aggregate($uid, time());

    // Optionally filter by entity.
    if (!empty($entity_type) && !empty($label)) {
      $entity = $this->getEntityByLabel($entity_type, $label);
      $message_ids = !empty($digests[$entity_type][$entity->id()]) ? $digests[$entity_type][$entity->id()] : [];
    }
    else {
      $message_ids = [];
      foreach ($digests as $entity_type => $entities) {
        foreach ($entities as $messages) {
          $message_ids = array_merge($message_ids, $messages);
        }
      }
    }
    return $this->getMessages($message_ids);
  }

  /**
   * Checks that the given view modes are supported by the given notifier.
   *
   * @param \Drupal\message_digest\Plugin\Notifier\DigestInterface $notifier
   *   The message digest notifier to check.
   * @param array $expected_view_modes
   *   An array of view mode IDs to check.
   *
   * @throws \RuntimeException
   *   Thrown when one or more of the given view mode IDs are not supported by
   *   the given notifier.
   */
  protected function assertNotifierViewModes(DigestInterface $notifier, array $expected_view_modes) {
    $plugin_definition = $notifier->getPluginDefinition();
    $actual_view_modes = array_combine($plugin_definition['viewModes'], $plugin_definition['viewModes']);
    if (!empty($diff = array_diff_key(array_flip($expected_view_modes), $actual_view_modes))) {
      $missing_view_modes = implode(', ', array_keys($diff));
      $id = $notifier->getPluginId();
      throw new \RuntimeException("The following view modes are not supported by the '$id' digest notifier: '$missing_view_modes'.");
    }
  }

  /**
   * Returns the Message entities for the given IDs.
   *
   * @param int[] $message_ids
   *   The message IDs.
   *
   * @return \Drupal\message\MessageInterface[]
   *   The Message entities.
   */
  protected function getMessages(array $message_ids) {
    $messages = [];
    try {
      $storage = \Drupal::entityTypeManager()->getStorage('message');
      /** @var \Drupal\message\MessageInterface[] $messages */
      $messages = $storage->loadMultiple($message_ids);
    }
    catch (InvalidPluginDefinitionException $e) {
      // If the 'message' entity type does not exist for some reason then there
      // are no messages.
    }
    return $messages;
  }

  /**
   * Returns the message digest notifier for the given interval.
   *
   * @param string $interval_id
   *   The ID of a message digest interval. Examples of predefined intervals are
   *   'daily' and 'weekly'.
   *
   * @return \Drupal\message_digest\Plugin\Notifier\DigestInterface
   *   The notifier.
   *
   * @throws \RuntimeException
   *   Thrown when the requested notifier does not exist.
   */
  protected function getMessageDigestNotifierForInterval($interval_id) {
    $digest_interval = $this->getMessageDigestInterval($interval_id)->getInterval();
    $notifiers = $this->getMessageDigestManager()->getNotifiers();
    foreach ($notifiers as $notifier) {
      if ($notifier->getInterval() === $digest_interval) {
        return $notifier;
      }
    }

    throw new \RuntimeException("No digest notifier found for interval '$interval_id'.");
  }

  /**
   * Returns the digest manager service.
   *
   * @return \Drupal\message_digest\DigestManagerInterface
   *   The digest manager service.
   */
  protected function getMessageDigestManager() {
    return \Drupal::service('message_digest.manager');
  }

  /**
   * Returns the requested digest interval.
   *
   * @param string $id
   *   The digest interval ID.
   *
   * @return \Drupal\message_digest\Entity\MessageDigestIntervalInterface
   *   The digest interval entity.
   */
  protected function getMessageDigestInterval($id) {
    try {
      /** @var \Drupal\message_digest\Entity\MessageDigestIntervalInterface $entity */
      $entity = \Drupal::entityTypeManager()
        ->getStorage('message_digest_interval')
        ->load($id);
      return $entity;
    }
    catch (InvalidPluginDefinitionException $e) {
      throw new \RuntimeException("Could not retrieve message digest interval '$id'.", 0, $e);
    }
  }

}
