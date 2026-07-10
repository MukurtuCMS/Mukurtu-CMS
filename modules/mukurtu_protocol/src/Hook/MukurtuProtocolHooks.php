<?php

declare(strict_types=1);

namespace Drupal\mukurtu_protocol\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Drupal\og\Entity\OgMembership;
use Drupal\og\OgMembershipInterface;
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
   * Implements hook_entity_operation() for OG memberships.
   *
   * Adds a per-row Block link to OG membership rows on the community and
   * protocol member list pages (only when the membership is not blocked).
   */
  #[Hook('entity_operation')]
  public function entityOperationOgMembership(EntityInterface $entity): array {
    if (!$entity instanceof OgMembership) {
      return [];
    }

    $group_type = $entity->getGroupEntityType();
    if ($group_type !== 'community' && $group_type !== 'protocol') {
      return [];
    }

    $operations = [];
    $state = $entity->getState();

    $owner = $entity->getOwner();
    $name = ($owner instanceof UserInterface) ? $owner->getDisplayName() : t('member');

    if ($state !== OgMembershipInterface::STATE_BLOCKED) {
      $block_url = Url::fromRoute('mukurtu_protocol.og_membership.block', ['og_membership' => $entity->id()]);
      if ($block_url->access()) {
        $operations['block'] = [
          'title' => t('Block @name', ['@name' => $name]),
          'url' => $block_url,
          'weight' => 20,
        ];
      }
    }

    if ($state === OgMembershipInterface::STATE_BLOCKED) {
      $unblock_url = Url::fromRoute('mukurtu_protocol.og_membership.unblock', ['og_membership' => $entity->id()]);
      if ($unblock_url->access()) {
        $operations['unblock'] = [
          'title' => t('Unblock @name', ['@name' => $name]),
          'url' => $unblock_url,
          'weight' => 20,
        ];
      }
    }

    return $operations;
  }

  /**
   * Implements hook_entity_operation_alter() for OG memberships.
   *
   * Renames the default OG membership "Edit" and "Delete" operations to use
   * Mukurtu-appropriate labels on community and protocol member pages.
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlterOgMembership(array &$operations, EntityInterface $entity): void {
    if (!$entity instanceof OgMembership) {
      return;
    }

    $group_type = $entity->getGroupEntityType();
    if ($group_type !== 'community' && $group_type !== 'protocol') {
      return;
    }

    $owner = $entity->getOwner();
    $name = ($owner instanceof UserInterface) ? $owner->getDisplayName() : t('member');

    if (isset($operations['edit'])) {
      $operations['edit']['title'] = t('Manage roles for @name', ['@name' => $name]);
    }

    if (isset($operations['delete'])) {
      $label = $group_type === 'community'
        ? t('Remove @name from community', ['@name' => $name])
        : t('Remove @name from protocol', ['@name' => $name]);
      $operations['delete']['title'] = $label;
    }
  }

  /**
   * Implements hook_form_alter().
   *
   * Scopes the bulk action dropdown on the members overview to show only
   * context-appropriate actions and community-friendly labels.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!str_starts_with($form_id, 'views_form_og_members_overview_default')) {
      return;
    }
    $entity_type_id = \Drupal::routeMatch()
      ->getRouteObject()
      ?->getDefault('entity_type_id');
    if (!isset($form['header']['og_membership_bulk_form']['action']['#options'])) {
      return;
    }
    $actions = &$form['header']['og_membership_bulk_form']['action']['#options'];
    if ($entity_type_id === 'community') {
      unset($actions['mukurtu_manage_protocol_roles_action']);
      $actions['og_membership_delete_action'] = (string) t('Remove user(s) from community');
      $actions['og_membership_block_action'] = (string) t('Block user(s) in community');
      $actions['og_membership_unblock_action'] = (string) t('Unblock user(s) in community');
    }
    elseif ($entity_type_id === 'protocol') {
      unset($actions['mukurtu_manage_community_roles_action']);
      $actions['og_membership_delete_action'] = (string) t('Remove user(s) from protocol');
      $actions['og_membership_block_action'] = (string) t('Block user(s) in protocol');
      $actions['og_membership_unblock_action'] = (string) t('Unblock user(s) in protocol');
    }
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
