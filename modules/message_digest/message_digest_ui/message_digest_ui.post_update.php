<?php

/**
 * @file
 * Post update functions for the Message Digest UI module.
 */

/**
 * Renames interval plugin IDs to remove disallowed '.' from the name.
 */
function message_digest_ui_post_update_rename_action_plugins() {
  /** @var \Drupal\system\Entity\Action[] $actions */
  $actions = \Drupal::entityTypeManager()->getStorage('action')->loadMultiple();
  foreach ($actions as $action) {
    if (strpos($action->id(), 'message_digest_interval') === 0) {
      $plugin_id = str_replace('.', ':', $action->get('plugin'));
      $action->setPlugin($plugin_id);
      $action->save();
    }
  }
}
