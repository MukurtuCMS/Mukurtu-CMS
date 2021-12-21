<?php

namespace Drupal\mukurtu_dictionary;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Language community entity.
 *
 * @see \Drupal\mukurtu_dictionary\Entity\LanguageCommunity.
 */
class LanguageCommunityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\mukurtu_dictionary\Entity\LanguageCommunityInterface $entity */

    switch ($operation) {

      case 'view':

        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished language community entities');
        }


        return AccessResult::allowedIfHasPermission($account, 'view published language community entities');

      case 'update':

        return AccessResult::allowedIfHasPermission($account, 'edit language community entities');

      case 'delete':

        return AccessResult::allowedIfHasPermission($account, 'delete language community entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add language community entities');
  }


}
