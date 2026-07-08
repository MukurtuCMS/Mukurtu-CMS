<?php

/**
 * @file
 * Post-update functions for the mukurtu_notifications module.
 */

use Drupal\Core\Config\FileStorage;

/**
 * Re-deploy the new-user-registration notification email template.
 *
 * Backstop for environments where hook_update_40019() was skipped because
 * the module's stored schema version was already past 40019 (e.g. from a
 * previously checked-out branch), leaving a stale/broken template and
 * mail_body display in place. Safe to run unconditionally since it's an
 * idempotent overwrite of the same config hook_update_40019() writes.
 */
function mukurtu_notifications_post_update_new_user_registration_email(): void {
  $config_path = \Drupal::service('extension.list.module')->getPath('mukurtu_notifications') . '/config/install';
  $source = new FileStorage($config_path);
  $config_storage = \Drupal::service('config.storage');

  $configs = [
    'message.template.mukurtu_new_user_registration',
    'core.entity_view_display.message.mukurtu_new_user_registration.mail_body',
  ];

  foreach ($configs as $config_name) {
    $config_storage->write($config_name, $source->read($config_name));
  }

  \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
}
