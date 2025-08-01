<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\og\Og;

/**
 * Access controller for the Community entity.
 *
 * @see \Drupal\mukurtu_protocol\Entity\Community.
 */
class CommunityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\mukurtu_protocol\Entity\CommunityInterface $entity */

    switch ($operation) {

      case 'view':

        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished community entities');
        }

        // If field_access_mode is "public", anyone can view.
        if ($entity->getSharingSetting() == 'public') {
          return AccessResult::allowedIfHasPermission($account, 'view published community entities');
        }

        // If field_access_mode is "community-only", only members can view.
        if ($entity->getSharingSetting() == 'community-only') {
          // Get membership.
          $membership = Og::getMembership($entity, $account);

          // Members can view community-only communities.
          if ($membership) {
            return AccessResult::allowedIfHasPermission($account, 'view published community entities');
          }
        }
        return AccessResult::forbidden();

      case 'update':
        // Only community managers have permission to edit communities.
        $membership = Og::getMembership($entity, $account);
        if ($membership && $membership->hasRole("community-community-community_manager")) {
          return AccessResult::allowed();
        }
        return AccessResult::forbidden();

      case 'delete':
        // Cannot delete a parent community.
        if ($entity->isParentCommunity()) {
          return AccessResult::forbidden();
        }

        // Cannot delete a community with protocols.
        $protocols = $entity->getProtocols();
        if (!empty($protocols)) {
          return AccessResult::forbidden();
        }
        return AccessResult::allowedIfHasPermission($account, 'delete community entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add community entities');
  }


}
