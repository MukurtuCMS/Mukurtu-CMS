<?php

declare(strict_types=1);

namespace Drupal\genpass\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Provide OOP help hook.
 */
class Help {

  use StringTranslationTrait;

  /**
   * Constructs a new SiteVerifyHooks object.
   */
  public function __construct(
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Provide helpful pointers for admins.
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    if ($route_name == 'help.page.genpass') {
      return implode("\n", [
        '<h3>',
        $this->t('About GenPass (Generate Password)'),
        '</h3>', '<p>',
        $this->t('The GenPass module provides a tool for generating strong and secure passwords during User registration and Admin User Creation. There are options to choose the length of password and who will be provided the password generated. Admins can be restricted from entering or setting user passwords.'),
        '</p>', '<h4>',
        $this->t('Replacement Password Generating Service'),
        '</h4>', '<p>',
        $this->t('The Drupal Core DefaultPasswordGenerator service can be replaced by the GenpassPasswordGenerator service to allow for more special characters to be included in the password by default, the ability to alter those characters sets (see below), and for at least one character from each set to be guaranteed to be included in the new password.'),
        '</p>', '<h4>',
        $this->t('Altering Password Character sets'),
        '</h4>', '<p>',
        $this->t('If the GenPass password generator is enabled, the characters used to generate passwords can be altered by a module implementing hook_genpass_character_sets_alter.'),
        '</p>',
      ]);
    }
  }

}
