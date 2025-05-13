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

        // If field_access_mode is open, anyone can view.
        if ($entity->getSharingSetting() == 'open') {
          return AccessResult::allowedIfHasPermission($account, 'view published community entities');
        }

        // If field_access_mode is strict, only members can view.
        if ($entity->getSharingSetting() == 'strict') {
          // Get membership.
          $membership = Og::getMembership($entity, $account);

          // Members can view strict communities.
          if ($membership) {
            return AccessResult::allowedIfHasPermission($account, 'view published community entities');
          }
        }
        return AccessResult::forbidden();

      case 'update':

        return AccessResult::allowedIfHasPermission($account, 'edit community entities');

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
