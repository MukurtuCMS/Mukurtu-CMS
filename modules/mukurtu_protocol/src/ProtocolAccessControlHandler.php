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

    if ($operation == 'view') {
      // Anybody can view during creation.
      if ($entity->isNew()) {
        return AccessResult::allowed();
      }

      // Anbody can view an open protocol.
      if ($entity->isOpen()) {
        return AccessResult::allowed();
      }

      // Users with an active membership in the protocol can view.
      $membership = Og::getMembership($entity, $account);
      if ($membership) {
        return AccessResult::allowed();
      }

      // Otherwise user must be a member of ALL owning communities to view.
      foreach ($communities as $community) {
        $membership = Og::getMembership($community, $account);
        if (!$membership) {
          return AccessResult::forbidden();
        }
      }

      return AccessResult::forbidden();
    }

    // Not attached to communities yet.
    // It's fine to update/delete.
    if ($entity->isNew()) {
      return AccessResult::allowed();
    }

    // If this protocol is attached to communities, user must have all
    // relevant OG permissions for each.
    if (!empty($communities)) {
      $ogAccessService = \Drupal::service('og.access');
      $result = AccessResult::allowed();

      // Check each community.
      foreach ($communities as $community) {
        $result = $result->andIf($ogAccessService->userAccessGroupContentEntityOperation($operation, $community, $entity, $account));
      }
      return $result;
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
          return AccessResult::allowed();
        }
      }
    }
    return AccessResult::forbidden();
  }

}
