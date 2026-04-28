<?php

namespace Drupal\mukurtu_core\Plugin\EntityReferenceSelection;

use Drupal\user\Plugin\EntityReferenceSelection\UserSelection;

/**
 * Provides entity reference selection for users with Mukurtu management roles.
 *
 * Returns users with Drupal roles administrator or mukurtu_manager, OG
 * community_manager role in any community, or OG protocol_steward role in any
 * protocol.
 *
 * @EntityReferenceSelection(
 *   id = "mukurtu_manager_users",
 *   label = @Translation("Mukurtu privileged users"),
 *   entity_types = {"user"},
 *   group = "mukurtu_manager_users",
 *   weight = 1
 * )
 */
class MukurtuManagerUserSelection extends UserSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);

    $uids = $this->getPrivilegedUserIds();

    if (empty($uids)) {
      $query->condition('uid', 0);
    }
    else {
      $query->condition('uid', $uids, 'IN');
    }

    return $query;
  }

  /**
   * Returns UIDs of all users with Mukurtu management roles.
   *
   * Results are statically cached per request since this is called on every
   * autocomplete keystroke.
   */
  protected function getPrivilegedUserIds(): array {
    static $cached = NULL;
    if ($cached !== NULL) {
      return $cached;
    }

    $uids = [];

    // Users with Drupal administrator or mukurtu_manager roles.
    $role_uids = \Drupal::entityQuery('user')
      ->condition('roles', ['administrator', 'mukurtu_manager'], 'IN')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();
    $uids = array_merge($uids, array_values($role_uids));

    // Users with the community_manager OG role in any community.
    $cm_ids = \Drupal::entityQuery('og_membership')
      ->condition('entity_type', 'community')
      ->condition('roles', 'community-community-community_manager')
      ->condition('state', 'active')
      ->accessCheck(FALSE)
      ->execute();
    if ($cm_ids) {
      $cm_memberships = \Drupal::entityTypeManager()
        ->getStorage('og_membership')
        ->loadMultiple($cm_ids);
      foreach ($cm_memberships as $membership) {
        $uids[] = $membership->getOwnerId();
      }
    }

    // Users with the protocol_steward OG role in any protocol.
    $ps_ids = \Drupal::entityQuery('og_membership')
      ->condition('entity_type', 'protocol')
      ->condition('roles', 'protocol-protocol-protocol_steward')
      ->condition('state', 'active')
      ->accessCheck(FALSE)
      ->execute();
    if ($ps_ids) {
      $ps_memberships = \Drupal::entityTypeManager()
        ->getStorage('og_membership')
        ->loadMultiple($ps_ids);
      foreach ($ps_memberships as $membership) {
        $uids[] = $membership->getOwnerId();
      }
    }

    $cached = array_unique($uids);
    return $cached;
  }

}
