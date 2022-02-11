<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Protocol control entity.
 *
 * @see \Drupal\mukurtu_protocol\Entity\ProtocolControl.
 */
class ProtocolControlAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface $entity */

    // For non-members we can deny immediately.
    if (!$entity->inGroup($account)) {
      return AccessResult::forbidden();
    }

    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished protocol control entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published protocol control entities');

      case 'update':
        if ($account->id() == 1) {
          return AccessResult::allowed();
        }
        // Can only update if the user can see ALL protocols involved
        // AND can edit the target entity.
        $targetEntity = $entity->getControlledEntity();
        /** @var \Drupal\Core\Entity\EntityInterface $targetEntity */
        if ($targetEntity && $targetEntity->access('update', $account)) {
          return $entity->inAllGroups($account) ? AccessResult::allowed() : AccessResult::forbidden();
        }
        return AccessResult::forbidden();

        //return AccessResult::allowedIfHasPermission($account, 'edit protocol control entities');

      case 'delete':
        // Only the system gets to delete PCEs.
        if ($account->id() == 1) {
          return AccessResult::allowed();
        }
        else {
          return AccessResult::forbidden();
        }
        //return AccessResult::allowedIfHasPermission($account, 'delete protocol control entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add protocol control entities');
  }

}
