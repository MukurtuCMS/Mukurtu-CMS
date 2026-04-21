<?php

namespace Drupal\dashboards\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access handler for dashboards.
 */
class DashboardAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation == 'delete' && $entity
      ->isNew()) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity);
    }
    if ($operation == 'view') {
      return AccessResult::allowedIfHasPermission($account, 'can view ' . $entity->id() . ' dashboard')
        ->orIf(AccessResult::allowedIfHasPermission($account, $this->entityType->getAdminPermission()));
    }
    if ($operation == 'edit') {
      return AccessResult::allowedIfHasPermission($account, $this->entityType->getAdminPermission())
        ->orIf(AccessResult::allowedIfHasPermission($account, 'can override ' . $entity->id() . ' dashboard'));
    }
    if ($this->entityType
      ->getAdminPermission()) {
      return AccessResult::allowedIfHasPermission($account, $this->entityType
        ->getAdminPermission());
    }
    else {
      return AccessResult::neutral();
    }
  }

}
