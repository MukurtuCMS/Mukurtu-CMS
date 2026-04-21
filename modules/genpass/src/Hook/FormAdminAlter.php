<?php

declare(strict_types=1);

namespace Drupal\genpass\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\genpass\GenpassInterface;

/**
 * Alter admin forms to change module settings.
 */
class FormAdminAlter {

  use StringTranslationTrait;

  /**
   * Constructs a new FormAdminAlter object.
   */
  public function __construct(
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Alter the config schema to fully validate GenPass settings.
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaAlter(&$definitions): void {
    // Add GenpassMode constraint to verify_mail schema to facilitate interlock.
    $ref = &$definitions['user.settings']['mapping']['verify_mail'];
    $ref['constraints']['GenpassMode'] = ['operationMode' => 'verify_mail'];
  }

  /**
   * Admin settings form at admin/config/people/accounts.
   */
  #[Hook('form_user_admin_settings_alter')]
  public function adminFormAlter(
    &$form,
    FormStateInterface $form_state,
    $form_id,
  ): void {

    // Place genpass configuration details above the system emails accordion,
    // and move the notification email to be with them.
    $form['mail_notification_address']['#weight'] = 8;
    $form['email']['#weight'] = 10;

    $form['genpass_config_registration'] = [
      '#type' => 'details',
      '#title' => $this->t('Generate Password - User Account Registration'),
      '#description' => $this->t('Options to alter the password entry field on Admin "Add user" form and User Registration form. A form must have the possibility of password entry for these settings to be relevant. If the "<a href="@url">Require email verification when a visitor creates an account</a>" is enabled, the password field is not added to user registration form, and this module cannot alter it.', [
        '@url' => Url::fromRoute(
          'entity.user.admin_form', [],
          ['fragment' => 'edit-user-email-verification']
        )->toString(),
      ]),
      '#open' => TRUE,
      '#weight' => 5,
    ];

    $form['genpass_config_registration']['genpass_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('User password entry'),
      '#config_target' => 'genpass.settings:genpass_mode',
      '#options' => [
        GenpassInterface::PASSWORD_REQUIRED => $this->t('Users <strong>must</strong> enter a password on registration. This is option is not available if email verification is enabled.'),
        GenpassInterface::PASSWORD_OPTIONAL => $this->t('Users <strong>may</strong> enter a password on registration. If left empty, a random password will be generated. This is option is not available if email verification is enabled.'),
        GenpassInterface::PASSWORD_RESTRICTED => $this->t('Users <strong>cannot</strong> enter a password on registration; a random password will be generated. This option is the only valid choice if email verification is enabled above.'),
      ],
      '#description' => $this->t(
        'Choose a password handling mode for user the registration form. The setting "<a href="@url">Require email verification when a visitor creates an account</a>" being set precludes the first two options from working.', [
          '@url' => Url::fromRoute(
            'entity.user.admin_form', [],
            ['fragment' => 'edit-user-email-verification']
          )->toString(),
        ]
      ),
    ];

    $form['genpass_config_registration']['genpass_admin_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Admin password entry'),
      '#config_target' => 'genpass.settings:genpass_admin_mode',
      '#options' => [
        GenpassInterface::PASSWORD_ADMIN_SHOW => $this->t('Admins <strong>may</strong> set a password when creating or editing an account.'),
        GenpassInterface::PASSWORD_ADMIN_HIDE => $this->t('Admins <strong>cannot</strong> set a password when creating or editing an account.'),
      ],
      '#description' => $this->t('Choose whether admins can set passwords. Admin can always set their own password.'),
    ];

    $form['genpass_config_registration']['genpass_display'] = [
      '#type' => 'radios',
      '#title' => $this->t('Generated password display'),
      '#config_target' => 'genpass.settings:genpass_display',
      '#options' => [
        GenpassInterface::PASSWORD_DISPLAY_NONE => $this->t('Do not display.'),
        GenpassInterface::PASSWORD_DISPLAY_ADMIN => $this->t('Display when site administrators create new user accounts.'),
        GenpassInterface::PASSWORD_DISPLAY_USER => $this->t('Display when users create their own accounts.'),
        GenpassInterface::PASSWORD_DISPLAY_BOTH => $this->t('Display to both site administrators and users.'),
      ],
      '#description' => $this->t('Whether or not the generated password should display after a user account is created via Admin Add Person, or User Registration Form.'),
    ];

    $form['genpass_config_generation'] = [
      '#type' => 'details',
      '#title' => $this->t('Generate Password - Generation Parameters'),
      '#description' => $this->t('Parameters used for generation of passwords using both DefaultPasswordGenerator and GenpassPasswordGenerator.'),
      '#open' => TRUE,
      '#weight' => 6,
    ];

    $form['genpass_config_generation']['genpass_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Generated password length'),
      '#config_target' => 'genpass.settings:genpass_length',
      '#size' => 2,
      '#maxlength' => 2,
      '#min' => 5,
      '#max' => 32,
      '#step' => 1,
      '#description' => $this->t('Set the length of generated passwords here. Allowed range: 5 to 32.'),
    ];

    // Option to override Drupal Core DefaultPasswordGenerator with Genpass.
    $form['genpass_config_core_service'] = [
      '#type' => 'details',
      '#title' => $this->t('Generate Password - Service Replacement'),
      '#description' => '<p>' . implode("</p>\n<p>", [
        $this->t('Genpass provides <a href="@genpass_gen_url">GenpassPasswordGenerator</a> as a replacement to the Drupal Core <a href="@core_gen_url">DefaultPasswordGenerator</a> to generate passwords. To compare the generation code, click the class names.', [
          '@genpass_gen_url' => 'https://git.drupalcode.org/project/genpass/-/blob/2.1.x/src/GenpassPasswordGenerator.php?ref_type=heads#L40-99',
          '@core_gen_url' => 'https://git.drupalcode.org/project/drupal/-/blob/11.x/core/lib/Drupal/Core/Password/DefaultPasswordGenerator.php#L22-42',
        ]),
        $this->t('The Genpass generator uses more special characters, and guarantees that at least one character from each of the four sets is included in the generated password: upper and lower case letters, digits, and special characters. The <a href="@cs_url">character sets</a> can be altered with a module implementing <a href="@hook_url">hook_genpass_character_sets_alter</a>.', [
          '@cs_url' => 'https://git.drupalcode.org/project/genpass/-/blob/2.1.x/src/GenpassPasswordGenerator.php#L142-153',
          '@hook_url' => 'https://git.drupalcode.org/project/genpass/-/blob/2.1.x/genpass.api.php#L13-31',
        ]),
        $this->t('When enabled, the GenpassPasswordGenerator will be used for all instances of password generation.'),
      ]) . '</p>',
      '#open' => FALSE,
      '#weight' => 6,

      'genpass_override_core' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Use Genpass as Core password generator'),
        '#config_target' => 'genpass.settings:genpass_override_core',
      ],
    ];

    $form['#submit'][] = [$this, 'settingsSubmit'];
  }

  /**
   * Flush caches on submit.
   */
  public function settingsSubmit($form, FormStateInterface $form_state): void {

    // Flush cache on settings change to update genpass_user_forms content.
    Cache::invalidateTags(['genpass']);
  }

}
