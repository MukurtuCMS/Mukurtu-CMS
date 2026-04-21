<?php

/**
 * @file
 * Hooks related to genpass module and password generation.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Build list of form_ids and settings to alter password field.
 *
 * @return array
 *   Array keyed by form_id and an array of form specific settings as value.
 */
function hook_genpass_user_forms() {

  return [
    // Auto-generate a password on the user profile form and display to user.
    'user_profile_form' => [
      'genpass_mode' => GenpassInterface::PASSWORD_RESTRICTED,
      'genpass_admin_mode' => GenpassInterface::PASSWORD_ADMIN_HIDE,
      'genpass_display' => GenpassInterface::PASSWORD_DISPLAY_USER,
      'genpass_password_field' => [['profile', 'password']],
      'genpass_notify_item' => [['not', 'applicable']],
    ],
  ];
}

/**
 * Alter which user form ids on which Genpass alters the password field.
 *
 * When editing an existing entity, the password field is always available to
 * the user, and admin's ability to see it is based on the Admin password entry
 * setting.
 *
 * All field altering settings are used when the entity is new.
 *
 * @param array $form_ids
 *   Array keyed by form_id and an array of form specific settings as value.
 */
function hook_genpass_user_forms_alter(array &$form_ids) {

  // Passwords are changed on the changepass form display.
  if (isset($form_ids['user_changepass_form'])) {
    $form_ids['user_changepass_form']['genpass_mode'] = GenpassInterface::PASSWORD_REQUIRED;
  }
}

/**
 * Alter the character sets used in genpass_password().
 *
 * The resulting altered character sets will be cached in the default bin
 * using CACHE_PERMANENT. A full cache flush will be required for this to
 * be called again, or flush the tag 'genpass'.
 *
 * @param array $character_sets
 *   A array of strings which make up separate character sets.
 *
 * @throws \Drupal\genpass\InvalidCharacterSetsException.
 *   In the event that the character set is too small to be used. Minimum size
 *   is the length of the password.
 */
function hook_genpass_character_sets_alter(array &$character_sets) {

  // Add the similar characters back in to annoy users.
  $character_sets['annoyingly_similar'] .= '`|I1l0O';
}

/**
 * @} End of "addtogroup hooks".
 */
