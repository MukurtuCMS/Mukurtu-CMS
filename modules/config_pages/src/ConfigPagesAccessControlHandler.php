<?php

namespace Drupal\config_pages;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the config page entity type.
 *
 * @see \Drupal\config_pages\Entity\ConfigPages
 */
class ConfigPagesAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation === 'view') {
      if ($entity->getEntityTypeId() === 'config_pages' && $account->hasPermission('view config_pages entity')) {
        return AccessResult::allowed()->cachePerPermissions();
      }
      if ($entity->getEntityTypeId() === 'config_pages' && $account->hasPermission('view ' . $entity->bundle() . ' config page entity')) {
        return AccessResult::allowed()->cachePerPermissions();
      }
    }
    if ($operation == 'update') {
      if ($account->hasPermission('edit config_pages entity')) {
        return AccessResult::allowed()->cachePerPermissions();
      }
      if ($entity->getEntityTypeId() === 'config_pages_type' && $account->hasPermission('edit ' . $entity->id() . ' config page entity')) {
        return AccessResult::allowed()->cachePerPermissions();
      }
      if ($entity->getEntityTypeId() === 'config_pages' && $account->hasPermission('edit ' . $entity->bundle() . ' config page entity')) {
        return AccessResult::allowed()->cachePerPermissions();
      }
    }
    return parent::checkAccess($entity, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    if ($account->hasPermission('edit config_pages entity')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    if ($account->hasPermission('edit ' . $entity_bundle . ' config page entity')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    return parent::checkCreateAccess($account, $context, $entity_bundle);
  }

}
