<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_protocol\Plugin\EntityReferenceSelection;

use Drupal\og\OgRoleInterface;
use Drupal\og\Plugin\EntityReferenceSelection\OgUserSelection;
use Drupal\mukurtu_protocol\Entity\Protocol;

/**
 * Provide Mukurtu User selection handler for memberships.
 *
 * @EntityReferenceSelection(
 *   id = "mukurtu_user_selection",
 *   label = @Translation("Mukurtu Membership user selection"),
 *   group = "mukurtu_user_selection",
 *   entity_types = {"user"},
 *   weight = 10
 * )
 */
class ProtocolUserSelection extends OgUserSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);

    // Get the group object (community/protocol).
    $group = NULL;
    if (isset($this->configuration['group'])) {
      $group = $this->configuration['group'];
    }

    if ($group) {
      // If we have a protocol, we want to limit the user selection to
      // the members of the owning community/communities.
      if ($group instanceof Protocol) {
        /** @var \Drupal\mukurtu_protocol\Entity\Protocol $group */
        $communities = $group->getCommunities();
        $membership_manager = \Drupal::service('og.membership_manager');

        $inCommunity = $query->orConditionGroup();
        // Build a list of UIDs for each owning community.
        foreach ($communities as $community) {
          $communityMembers = [];
          $memberships = $membership_manager->getGroupMembershipsByRoleNames($community, [OgRoleInterface::AUTHENTICATED]);
          foreach ($memberships as $membership) {
            $uid = $membership->getOwnerId();
            $communityMembers[$uid] = $uid;
          }

          // Add to the OR condition.
          if ($communityMembers) {
            $inCommunity->condition('uid', $communityMembers, 'IN');
          }
        }

        // Attach the entire OR condition.
        $query->condition($inCommunity);
      }
    }

    return $query;
  }

}
