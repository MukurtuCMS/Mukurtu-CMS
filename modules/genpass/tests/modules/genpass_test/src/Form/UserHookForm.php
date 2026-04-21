<?php

declare(strict_types=1);

namespace Drupal\genpass_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provide a custom form with username and password to create a new user.
 */
class UserHookForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'genpass_test_user_hook_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['fields_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => 'Credentials',

      'credential_user' => [
        '#type' => 'textfield',
        '#title' => 'Username',
        '#required' => TRUE,
      ],

      'credential_pass' => [
        '#type' => 'password_confirm',
        '#title' => 'Password',
        '#size' => 60,
        '#required' => TRUE,
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',

      'submit' => [
        '#type' => 'submit',
        '#value' => 'Submit',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
