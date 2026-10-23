<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\og\Og;
use Drupal\mukurtu_protocol\CulturalProtocols;
use Drupal\mukurtu_protocol\Entity\ProtocolInterface;

/**
 * Access controller for the Protocol entity.
 *
 * @see \Drupal\mukurtu_protocol\Entity\Protocol.
 */
class ProtocolAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface $entity */

    // Not attached to communities yet, we don't care.
    if ($entity->isNew()) {
      return AccessResult::allowed();
    }

    if ($operation == 'view') {
      // Anybody can view an open protocol. Depends on entity fields.
      if ($entity->isOpen()) {
        return AccessResult::allowed()->addCacheableDependency($entity);
      }

      // Users with an active membership in the protocol can view,
      // unless they are blocked in a parent community.
      $membership = Og::getMembership($entity, $account);
      if ($membership) {
        if (CulturalProtocols::isUserBlockedFromProtocolViaCommunity($account, $entity)) {
          return AccessResult::forbidden()
            ->addCacheableDependency($entity)
            ->addCacheableDependency($membership)
            ->addCacheTags(["user:{$account->id()}"]);
        }
        return AccessResult::allowed()->addCacheableDependency($membership);
      }

      // Otherwise user must be a member of ALL owning communities
      // and have protocol update permission to view.
      return $this->checkAccess($entity, 'update', $account);
    }

    if ($operation == 'update') {
      // Protocol stewards can always edit their protocols.
      $membership = Og::getMembership($entity, $account);
      if ($membership && $membership->hasRole("protocol-protocol-protocol_steward")) {
        return AccessResult::allowed()->addCacheableDependency($membership);
      }
      // Fall through to community membership check below.
    }

    // These are checks that happen regardless of OG specific permissions.
    if ($operation == 'delete') {
      if ($entity->inUse()) {
        // Tag with node_list and media_list so this cached "forbidden" is
        // invalidated when content referencing this protocol is deleted.
        return AccessResult::forbidden()->addCacheTags(['node_list', 'media_list']);
      }
    }

    return $this->checkCommunityPermissions($entity, $operation, $account);
  }

  /**
   * Checks delete-form access for a protocol.
   *
   * Entity delete access is forbidden while content still uses the protocol.
   * The delete form is still reachable for users who would otherwise be
   * allowed to delete, so the form can explain what is blocking the delete.
   *
   * @param \Drupal\mukurtu_protocol\Entity\ProtocolInterface $protocol
   *   The protocol.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function deleteFormAccess(ProtocolInterface $protocol, AccountInterface $account): AccessResultInterface {
    $access = $this->access($protocol, 'delete', $account, TRUE);
    if ($access->isAllowed() || !$protocol->inUse()) {
      return $access->addCacheTags(['node_list', 'media_list']);
    }
    return $this->checkCommunityPermissions($protocol, 'delete', $account);
  }

  /**
   * Checks the OG permissions for an operation in each owning community.
   *
   * @param \Drupal\mukurtu_protocol\Entity\ProtocolInterface $entity
   *   The protocol.
   * @param string $operation
   *   The operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkCommunityPermissions(ProtocolInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    $communities = $entity->getCommunities();

    // If this protocol is attached to communities, user must have all
    // relevant OG permissions for each.
    if (!empty($communities)) {
      // Accumulate cache metadata from each community membership consulted.
      $cacheability = new CacheableMetadata();
      if ($operation == 'delete') {
        // Ensure the allowed result is also invalidated when content changes,
        // covering the case where a protocol transitions from empty to in-use.
        $cacheability->addCacheTags(['node_list', 'media_list']);
      }

      // Check operation for each community.
      foreach ($communities as $community) {
        // Get the membership for this specific community.
        $membership = Og::getMembership($community, $account);

        // Deny immediately if any memberships are missing. Tag with user:{id}
        // so Community::addMember() can invalidate this cached result.
        if (!$membership) {
          return AccessResult::forbidden()
            ->addCacheTags(["user:{$account->id()}"])
            ->addCacheableDependency($cacheability);
        }

        // Carry the membership as a cache dependency so that role changes
        // (which call $membership->save()) auto-invalidate this result.
        $cacheability->addCacheableDependency($membership);

        // If they have 'any' we can skip to the next community.
        if ($membership->hasPermission("$operation any protocol protocol")) {
          continue;
        }

        // Check for ownership + own permission.
        if (!($membership->hasPermission("$operation own protocol protocol") && $entity->getOwnerId() == $account->id())) {
          return AccessResult::forbidden()->addCacheableDependency($cacheability);
        }
      }

      return AccessResult::allowed()->addCacheableDependency($cacheability);
    }

    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $memberships = Og::getMemberships($account);

    foreach ($memberships as $membership) {
      if ($membership->getGroupEntityType() == 'community') {
        if ($membership->hasPermission("create protocol protocol")) {
          return AccessResult::allowed()->addCacheableDependency($membership);
        }
      }
    }

    // Tag with user:{id} so Community::addMember() invalidates this cached
    // forbidden when the user gains their first community membership.
    return AccessResult::forbidden()->addCacheTags(["user:{$account->id()}"]);
  }

}
