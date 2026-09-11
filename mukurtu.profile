<?php

/**
 * @file
 * Enables modules and site configuration for a standard site installation.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter() for install_configure_form().
 */
function mukurtu_form_install_configure_form_alter(array &$form, FormStateInterface $form_state): void {
  // Appending to #submit means the callback runs after core's own handler has
  // saved system.site, so the site email address is available to it. The
  // profile's own hook_install() cannot do this work: install_install_profile
  // runs before install_configure_form, so system.site:mail is still empty
  // while mukurtu_install() executes.
  $form['#submit'][] = 'mukurtu_install_configure_form_submit';
}

/**
 * Submit callback for install_configure_form().
 *
 * Sends Website feedback form submissions to the site email address.
 * contact.form.feedback ships with the admin@example.com placeholder that
 * Drupal's own standard profile uses, and nothing in core substitutes it during
 * installation, so without this the form mails an address nobody reads.
 *
 * @param array $form
 *   The form array.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current form state.
 *
 * @internal
 */
function mukurtu_install_configure_form_submit(array $form, FormStateInterface $form_state): void {
  $site_mail = $form_state->getValue('site_mail')
    ?: \Drupal::config('system.site')->get('mail');
  if (empty($site_mail)) {
    return;
  }

  $feedback_form = \Drupal::configFactory()->getEditable('contact.form.feedback');
  if ($feedback_form->isNew()) {
    return;
  }

  $feedback_form->set('recipients', [$site_mail])->save();
}
