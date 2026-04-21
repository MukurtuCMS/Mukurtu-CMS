<?php

declare(strict_types=1);

namespace Drupal\genpass\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\genpass\GenpassInterface;

/**
 * Provide OO hook for genpass_user_forms.
 */
class UserForms {

  /**
   * Constructs a new UserForms object.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Provide the default core user registration settings.
   */
  #[Hook('genpass_user_forms')]
  public function genpassUserForms(): array {

    // Provide the forms this module normally alters via same hook.
    $settings = $this->configFactory->get('genpass.settings');
    // phpcs:disable
    $common_settings_array = [

      // User password entry.
      'genpass_mode' => $settings->get('genpass_mode')
        ?? GenpassInterface::PASSWORD_RESTRICTED,

      // Admin password entry.
      'genpass_admin_mode' => $settings->get('genpass_admin_mode')
        ?? GenpassInterface::PASSWORD_ADMIN_SHOW,

      // Generated password display.
      'genpass_display' => $settings->get('genpass_display')
        ?? GenpassInterface::PASSWORD_DISPLAY_NONE,

      // Password field parents arrays.
      'genpass_password_field' => [
        ['account', 'pass'],
        ['pass'],
      ],

      // Notification field to add admin hint.
      'genpass_notify_item' => [
        ['account', 'notify'],
        ['notify'],
      ],
    ];
    // phpcs:enable

    return [
      'user_register_form' => $common_settings_array,
      'user_form' => $common_settings_array,
    ];
  }

}
