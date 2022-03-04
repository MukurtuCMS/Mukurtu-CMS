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
    /** @var \Drupal\Core\Entity\EntityInterface $targetEntity */
    $targetEntity = $entity->getControlledEntity();
    $new = is_null($targetEntity);

    // For existing content we can deny users who
    // cannot see all involved protocols immediately
    // for any operation.
    if (!$new && !$entity->inAllGroups($account)) {
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

        // For new content, let anybody create.
        if ($new) {
          return AccessResult::allowedIfHasPermission($account, 'add protocol control entities');
        }

        // For existing content, user needs edit access to the
        // controlled entity.
        if ($targetEntity->access('update', $account)) {
          return AccessResult::allowedIfHasPermission($account, 'edit protocol control entities');
        }
        return AccessResult::forbidden();

      case 'delete':
        // Only the system gets to delete PCEs.
        return $account->id() == 1 ? AccessResult::allowed() : AccessResult::forbidden();

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
