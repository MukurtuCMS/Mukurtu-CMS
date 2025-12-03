<?php

declare(strict_types=1);

namespace Drupal\mukurtu_protocol\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Mukurtu protocol hooks.
 */
class MukurtuProtocolHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'community' => [
        'render element' => 'elements',
        'file' => 'community.page.inc',
      ],
      'protocol' => [
        'render element' => 'elements',
        'file' => 'protocol.page.inc',
      ],
      'communities_page' => [
        'variables' => [
          'communities' => NULL,
        ],
      ],
      'manage_community' => [
        'variables' => [
          'community' => NULL,
          'links' => NULL,
          'sharing' => NULL,
          'membership_display' => NULL,
          'members' => NULL,
          'description' => NULL,
          'protocols' => NULL,
          'notices' => NULL,
        ],
      ],
      'manage_protocol' => [
        'variables' => [
          'protocol' => NULL,
          'links' => NULL,
          'sharing' => NULL,
          'membership_display' => NULL,
          'members' => NULL,
          'description' => NULL,
          'communities' => NULL,
          'comment_status' => NULL,
        ],
      ],
      'community_protocol_list' => [
        'variables' => [
          'title' => NULL,
          'items' => [],
        ],
      ],
    ];
  }

}
