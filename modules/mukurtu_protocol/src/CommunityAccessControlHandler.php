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
          return AccessResult::allowedIfHasPermission($account, 'view unpublished community entities')
            ->addCacheableDependency($entity);
        }

        // If field_access_mode is "public", anyone can view.
        if ($entity->getSharingSetting() == 'public') {
          return AccessResult::allowedIfHasPermission($account, 'view published community entities')
            ->addCacheableDependency($entity);
        }

        // If field_access_mode is "community-only", only members can view.
        if ($entity->getSharingSetting() == 'community-only') {
          // Get membership.
          $membership = Og::getMembership($entity, $account);

          // Members can view community-only communities.
          if ($membership) {
            return AccessResult::allowedIfHasPermission($account, 'view published community entities')
              ->addCacheableDependency($entity)
              ->addCacheableDependency($membership);
          }
        }
        // Forbidden: depends on sharing setting (entity) and membership status.
        return AccessResult::forbidden()
          ->addCacheableDependency($entity)
          ->addCacheTags(["user:{$account->id()}"]);

      case 'update':
        // Only community managers have permission to edit communities.
        $membership = Og::getMembership($entity, $account);
        if ($membership && $membership->hasRole("community-community-community_manager")) {
          return AccessResult::allowed()->addCacheableDependency($membership);
        }
        // Tag with the membership if it exists (role mismatch), or user:{id}
        // so Community::addMember() can invalidate the cached result.
        $result = AccessResult::forbidden();
        return $membership
          ? $result->addCacheableDependency($membership)
          : $result->addCacheTags(["user:{$account->id()}"]);

      case 'delete':
        // Cannot delete a parent community. Depends on entity structure.
        if ($entity->isParentCommunity()) {
          return AccessResult::forbidden()->addCacheableDependency($entity);
        }

        // Cannot delete a community with protocols. Depends on entity field.
        $protocols = $entity->getProtocols();
        if (!empty($protocols)) {
          return AccessResult::forbidden()->addCacheableDependency($entity);
        }
        return AccessResult::allowedIfHasPermission($account, 'delete community entities')
          ->addCacheableDependency($entity);
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
