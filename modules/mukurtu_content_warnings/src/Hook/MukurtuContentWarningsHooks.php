<?php

declare(strict_types=1);

namespace Drupal\mukurtu_content_warnings\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Mukurtu content warning hooks.
 */
class MukurtuContentWarningsHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'mukurtu_content_warnings' => [
        'variables' => [
          'media' => NULL,
          'warnings' => NULL,
        ],
      ],
      'mukurtu_content_warning' => [
        'variables' => [
          'warning' => NULL,
        ],
      ],
    ];
  }

}
