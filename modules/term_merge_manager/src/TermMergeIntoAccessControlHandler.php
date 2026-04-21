<?php

namespace Drupal\term_merge_manager;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Term merge into entity.
 *
 * @see \Drupal\term_merge_manager\Entity\TermMergeInto.
 */
class TermMergeIntoAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\term_merge_manager\Entity\TermMergeIntoInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished term merge into entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published term merge into entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit term merge into entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete term merge into entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add term merge into entities');
  }

}
