<?php

namespace Drupal\message_digest\Traits;

use Behat\Gherkin\Node\TableNode;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use PHPUnit\Framework\Assert;

/**
 * Behat step definitions for the Message Digest module.
 *
 * This code is reused both by the (deprecated) MessageDigestSubContext and the
 * (recommended) MessageDigestContext.
 *
 * @see \MessageDigestSubContext
 * @see \Drupal\Tests\message_digest\Behat\MessageDigestContext
 */
trait MessageDigestContextTrait {

  use MessageDigestTrait;

  /**
   * Checks that the digest for a user contains a certain message.
   *
   * Example table:
   * @codingStandardsIgnoreStart
   *   | mail_subject | The content titled "My content" has been deleted       |
   *   | mail_body    | The "My content" page was deleted by an administrator. |
   * @codingStandardsIgnoreEnd
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   A table containing the expected content of the different view modes for
   *   the message.
   * @param string $interval
   *   The digest interval, e.g. 'daily' or 'weekly'.
   * @param string $username
   *   The name of the user for which the message is intended.
   * @param string|null $label
   *   Optional label of an entity that is related to the message.
   * @param string|null $entity_type
   *   Optional entity type of an entity that is related to the message.
   *
   * @Then the :interval digest for :username should contain the following message for the :label :entity_type:
   * @Then the :interval digest for :username should contain the following message:
   */
  public function assertDigestContains(TableNode $table, $interval, $username, $label = NULL, $entity_type = NULL) {
    // Check that the passed table contains valid view modes.
    $rows = $table->getRowsHash();
    $view_modes = array_keys($rows);
    $notifier = $this->getMessageDigestNotifierForInterval($interval);
    $this->assertNotifierViewModes($notifier, $view_modes);

    $user = user_load_by_name($username);
    $messages = $this->getUserMessagesByNotifier($notifier, $user->id(), $entity_type, $label);
    if (empty($messages)) {
      throw new \RuntimeException("The $interval digest for $username does not contain any messages related to the '$label' $entity_type.");
    }

    foreach ($messages as $message) {
      $found_view_modes = [];
      foreach ($rows as $view_mode => $expected_content) {
        $actual_content = $this->getRenderedMessage($message, $view_mode);
        if (strpos($actual_content, $expected_content) !== FALSE) {
          $found_view_modes[] = $view_mode;
        }
      }
      if (empty(array_diff($view_modes, $found_view_modes))) {
        // We found the message.
        return;
      }
    }
    $exception_message = !empty($entity_type) && !empty($label) ? "The expected message for the '$label' $entity_type was not found in the $interval digest for $username." : "The expected message was not found in the $interval digest for $username.";
    throw new \RuntimeException($exception_message);
  }

  /**
   * Checks that the digest for a user does not contain a certain message.
   *
   * Example table:
   * @codingStandardsIgnoreStart
   *   | mail_subject | The content titled "My content" has been deleted       |
   *   | mail_body    | The "My content" page was deleted by an administrator. |
   * @codingStandardsIgnoreEnd
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   A table containing the expected content of the different view modes for
   *   the message that should not be present.
   * @param string $interval
   *   The digest interval, e.g. 'daily' or 'weekly'.
   * @param string $username
   *   The name of the user for which the message is intended.
   * @param string|null $label
   *   Optional label of an entity that is related to the message.
   * @param string|null $entity_type
   *   Optional entity type of an entity that is related to the message.
   *
   * @Then the :interval digest for :username should not contain the following message for the :label :entity_type:
   * @Then the :interval digest for :username should not contain the following message:
   */
  public function assertDigestNotContains(TableNode $table, $interval, $username, $label = NULL, $entity_type = NULL) {
    // Check that the passed table contains valid view modes.
    $rows = $table->getRowsHash();
    $view_modes = array_keys($rows);
    $notifier = $this->getMessageDigestNotifierForInterval($interval);
    $this->assertNotifierViewModes($notifier, $view_modes);

    $user = user_load_by_name($username);
    $messages = $this->getUserMessagesByNotifier($notifier, $user->id(), $entity_type, $label);
    foreach ($messages as $message) {
      $found_view_modes = [];
      foreach ($rows as $view_mode => $expected_content) {
        $actual_content = $this->getRenderedMessage($message, $view_mode);
        if (strpos($actual_content, $expected_content) !== FALSE) {
          $found_view_modes[] = $view_mode;
        }
      }
      if (empty(array_diff($view_modes, $found_view_modes))) {
        // We found the message.
        $exception_message = !empty($entity_type) && !empty($label) ? "The message for the '$label' $entity_type was unexpectedly found in the $interval digest for $username." : "The message was unexpectedly found in the $interval digest for $username.";
        throw new \RuntimeException($exception_message);
      }
    }
  }

  /**
   * Checks that the given digest for a user does not contain any messages.
   *
   * @param string $interval
   *   The digest interval, e.g. 'daily' or 'weekly'.
   * @param string $username
   *   The name of the user who should not have any messages in their digest.
   *
   * @Then the :interval digest for :username should not contain any messages
   */
  public function assertDigestEmpty($interval, $username) {
    $notifier = $this->getMessageDigestNotifierForInterval($interval);

    $user = user_load_by_name($username);
    $messages = $this->getUserMessagesByNotifier($notifier, $user->id());
    Assert::assertEmpty($messages, "The $interval digest for $username is empty.");
  }

  /**
   * Delivers all message digests.
   *
   * @Given all message digests have been delivered
   *
   * @throws \Exception
   *   Thrown in case an error occurred while running the cron job.
   */
  public function deliverAllDigests() {
    $this->expireDigestMessages();
    do {
      $this->expireMessageDigestNotifiers();
      $this->getDriver()->runCron();
    } while ($this->countAllUndeliveredDigestMessages());
  }

  /**
   * Returns the entity with the given type, bundle and label.
   *
   * If multiple entities have the same label then the first one is returned.
   *
   * @param string $entity_type
   *   The entity type to check.
   * @param string $label
   *   The label to check.
   * @param string|null $bundle
   *   Optional bundle to check. If omitted, the entity can be of any bundle.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The requested entity.
   *
   * @throws \RuntimeException
   *   Thrown when an entity with the given type, label and bundle does not
   *   exist.
   */
  protected function getEntityByLabel($entity_type, $label, $bundle = NULL) {
    $entity_manager = \Drupal::entityTypeManager();
    try {
      $storage = $entity_manager->getStorage($entity_type);
    }
    catch (InvalidPluginDefinitionException $e) {
      throw new \RuntimeException("Storage for entity type '$entity_type' not found", NULL, $e);
    }
    $entity = $entity_manager->getDefinition($entity_type);

    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($entity->getKey('label'), $label)
      ->range(0, 1);

    // Optionally filter by bundle.
    if ($bundle) {
      $query->condition($entity->getKey('bundle'), $bundle);
    }

    $result = $query->execute();

    if ($result) {
      $result = reset($result);
      return $storage->load($result);
    }

    throw new \RuntimeException("The entity with label '$label' was not found.");
  }

}
