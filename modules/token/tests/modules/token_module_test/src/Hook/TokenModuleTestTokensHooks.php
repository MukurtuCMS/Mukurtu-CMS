<?php

namespace Drupal\token_module_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for token_module_test.
 */
class TokenModuleTestTokensHooks {

  /**
   * Implements hook_token_info()
   */
  #[Hook('token_info')]
  public function tokenInfo() {
    $info['tokens']['node']['colons:in:name'] = [
      'name' => t('A test token with colons in the name'),
      'description' => NULL,
    ];
    return $info;
  }

}
