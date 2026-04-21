<?php

/**
 * @file
 * Post update functions for Message Digest.
 */

use Drupal\Core\Database\Database;

/**
 * Delete orphaned messages.
 */
function message_digest_post_update_delete_orphaned_messages() {
  $connection = Database::getConnection();

  // Retrieve a list of all references to messages.
  $query = $connection->select('message_digest', 'md');
  $query->fields('md', ['mid']);
  $query->distinct();
  $referenced_message_ids = $query->execute()->fetchCol();

  // Retrieve a list of actual messages.
  $query = \Drupal::entityQuery('message');
  $existing_message_ids = $query->execute();

  // Delete references to messages that no longer exist.
  $orphaned_message_ids = array_diff($referenced_message_ids, $existing_message_ids);
  if (!empty($orphaned_message_ids)) {
    $connection->delete('message_digest')
      ->condition('mid', $orphaned_message_ids, 'IN')
      ->execute();
  }

  // Retrieve a list of all references to users.
  $query = $connection->select('message_digest', 'md');
  $query->fields('md', ['receiver']);
  $query->distinct();
  $referenced_user_ids = $query->execute()->fetchCol();

  // Retrieve a list of actual users.
  $query = \Drupal::entityQuery('user')->accessCheck(FALSE);
  $existing_user_ids = $query->execute();

  // Delete references to users that no longer exist.
  $orphaned_user_ids = array_diff($referenced_user_ids, $existing_user_ids);
  if (!empty($orphaned_user_ids)) {
    $connection->delete('message_digest')
      ->condition('receiver', $orphaned_user_ids, 'IN')
      ->execute();
  }

}
