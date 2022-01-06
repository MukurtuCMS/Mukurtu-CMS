<?php

namespace Drupal\mukurtu_collection;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Personal collection entity.
 *
 * @see \Drupal\mukurtu_collection\Entity\PersonalCollection.
 */
class PersonalCollectionAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\mukurtu_collection\Entity\PersonalCollectionInterface $entity */

    switch ($operation) {

      case 'view':
        // Private PCs can only be viewed by owners.
        if ($entity->isPrivate() && $entity->getOwnerId() != $account->id()) {
          return AccessResult::forbidden();
        }

        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished personal collection entities');
        }

        return AccessResult::allowedIfHasPermission($account, 'view published personal collection entities');

      case 'update':
        // Only owners can update.
        if ($entity->getOwnerId() != $account->id()) {
          return AccessResult::forbidden();
        }
        return AccessResult::allowedIfHasPermission($account, 'edit personal collection entities');

      case 'delete':
        // Only owners can delete.
        if ($entity->getOwnerId() != $account->id()) {
          return AccessResult::forbidden();
        }
        return AccessResult::allowedIfHasPermission($account, 'delete personal collection entities');
    }

    // Default to deny.
    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add personal collection entities');
  }


}
