<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

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


        return AccessResult::allowedIfHasPermission($account, 'view published community entities');

      case 'update':

        return AccessResult::allowedIfHasPermission($account, 'edit community entities');

      case 'delete':

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
