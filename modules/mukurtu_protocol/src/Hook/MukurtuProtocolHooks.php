<?php

declare(strict_types=1);

namespace Drupal\mukurtu_protocol\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Mukurtu protocol hooks.
 */
class MukurtuProtocolHooks {

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {
    if (!$entity instanceof UserInterface) {
      return [];
    }
    $url = Url::fromRoute('mukurtu_protocol.user_memberships', ['user' => $entity->id()]);
    if (!$url->access()) {
      return [];
    }
    return [
      'memberships' => [
        'title' => t('Memberships'),
        'url' => $url,
        'weight' => 20,
      ],
    ];
  }

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
      'browse_by_community_block' => [
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
      'mukurtu_user_memberships' => [
        'variables' => [
          'communities' => [],
          'orphan_protocols' => [],
          'user' => NULL,
        ],
      ],
    ];
  }

}
