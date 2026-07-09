<?php

/**
 * @file
 * Post-update functions for the mukurtu_notifications module.
 */

/**
 * Remove the mukurtu_new_user_registration message template.
 *
 * Backstop for environments where hook_update_40019() was skipped because
 * the module's stored schema version was already past 40019 (e.g. from a
 * previously checked-out branch), leaving the now-unused template and its
 * displays in place. Safe to run unconditionally since it's an idempotent
 * deletion of the same config hook_update_40019() removes.
 */
function mukurtu_notifications_post_update_new_user_registration_email(): void {
  $config_storage = \Drupal::service('config.storage');

  $configs = [
    'message.template.mukurtu_new_user_registration',
    'core.entity_view_display.message.mukurtu_new_user_registration.mail_body',
    'core.entity_view_display.message.mukurtu_new_user_registration.mail_subject',
    'field.field.message.mukurtu_new_user_registration.field_user',
  ];

  foreach ($configs as $config_name) {
    $config_storage->delete($config_name);
  }

  $config_path = \Drupal::service('extension.list.module')->getPath('mukurtu_notifications') . '/config/install';
  $source = new \Drupal\Core\Config\FileStorage($config_path);
  $config_storage->write('views.view.mukurtu_message_log', $source->read('views.view.mukurtu_message_log'));

  \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
}
