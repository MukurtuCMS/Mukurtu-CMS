<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for the mukurtu_import_strategy config entity.
 */
class MukurtuImportStrategyAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    assert($entity instanceof MukurtuImportStrategyInterface);

    if ($account->hasPermission('administer mukurtu_import_strategy')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $is_owner = (int) $entity->getOwnerId() === (int) $account->id();

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermissions($account, [
          'create mukurtu_import_strategy',
          'edit own mukurtu_import_strategy',
          'edit any mukurtu_import_strategy',
        ], 'OR')->cachePerPermissions();

      case 'update':
        if ($account->hasPermission('edit any mukurtu_import_strategy')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        if ($is_owner && $account->hasPermission('edit own mukurtu_import_strategy')) {
          return AccessResult::allowed()->cachePerPermissions()->cachePerUser();
        }
        return AccessResult::forbidden()->cachePerPermissions()->cachePerUser();

      case 'delete':
        if ($account->hasPermission('delete any mukurtu_import_strategy')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        if ($is_owner && $account->hasPermission('delete own mukurtu_import_strategy')) {
          return AccessResult::allowed()->cachePerPermissions()->cachePerUser();
        }
        return AccessResult::forbidden()->cachePerPermissions()->cachePerUser();
    }

    return parent::checkAccess($entity, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    if ($account->hasPermission('administer mukurtu_import_strategy')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    return AccessResult::allowedIfHasPermission($account, 'create mukurtu_import_strategy');
  }

}
