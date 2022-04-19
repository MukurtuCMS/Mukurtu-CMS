<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\og\Og;

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
    $communities = $entity->getCommunities();

    // Not attached to communities yet, we don't care.
    if ($entity->isNew()) {
      return AccessResult::allowed();
    }

    if ($operation == 'view') {
      // Anbody can view an open protocol.
      if ($entity->isOpen()) {
        return AccessResult::allowed();
      }

      // Users with an active membership in the protocol can view.
      $membership = Og::getMembership($entity, $account);
      if ($membership) {
        return AccessResult::allowed();
      }

      // Otherwise user must be a member of ALL owning communities
      // and have protocol update permission to view.
      return $this->checkAccess($entity, 'update', $account);
    }

    // These are checks that happen regardless of OG specific permissions.
    if ($operation == 'delete') {
      if ($entity->inUse()) {
        return AccessResult::forbidden();
      }
    }

    // If this protocol is attached to communities, user must have all
    // relevant OG permissions for each.
    if (!empty($communities)) {
      // Check operation for each community.
      foreach ($communities as $community) {
        // Get the membership for this specific community.
        $membership = Og::getMembership($community, $account);

        // Deny immediately if any memberships are missing.
        if (!$membership) {
          return AccessResult::forbidden();
        }

        // If they have 'any' we can skip to the next community.
        if ($membership->hasPermission("$operation any protocol protocol")) {
          continue;
        }

        // Check for ownership + own permission.
        if (!($membership->hasPermission("$operation own protocol protocol") && $entity->getOwnerId() == $account->id())) {
          return AccessResult::forbidden();
        }
      }

      return AccessResult::allowed();
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

    return AccessResult::forbidden();
  }

}
