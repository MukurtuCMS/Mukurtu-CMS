<?php

/**
 * @file
 * Defining the API part of the Message UI module.
 */

namespace Drupal\message_ui;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\Entity\Message;

/**
 * Alter the output of the message.
 *
 * @param array $build
 *   The build element.
 * @param \Drupal\message\Entity\Message $message
 *   The message object.
 */
function hook_message_ui_view_alter(array &$build, Message $message) {
  // Check the output of the message as you wish.
}

/**
 * Impact the message access control.
 *
 * @param \Drupal\message\Entity\Message $message
 *   The message object.
 * @param string $op
 *   The operation.
 * @param \Drupal\Core\Session\AccountInterface $account
 *   The account object.
 *
 * @return \Drupal\Core\Access\AccessResultAllowed
 *   The access result.
 */
function hook_message_message_ui_access_control(Message $message, $op, AccountInterface $account) {
  return AccessResult::allowed();
}

/**
 * Impact the message access control when creating the message.
 *
 * @param string $template
 *   The template ID.
 * @param \Drupal\Core\Session\AccountInterface $account
 *   The account object.
 *
 * @return \Drupal\Core\Access\AccessResultAllowed
 *   The access results.
 */
function hook_message_message_ui_create_access_control($template, AccountInterface $account) {
  return AccessResult::allowed();
}

/**
 * Altering the query object when deleting multiple message when using the form.
 *
 * @param \Drupal\Core\Entity\Query\QueryInterface $query
 *   The query object.
 */
function hook_message_ui_multiple_message_delete_query_alter(QueryInterface $query) {
  $query->condition('field_node_ref.target_id', 22);
}
