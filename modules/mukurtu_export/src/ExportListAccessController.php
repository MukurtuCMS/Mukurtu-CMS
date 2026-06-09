<?php

namespace Drupal\mukurtu_export;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

class ExportListAccessController extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\mukurtu_export\Entity\ExportList $entity */
    if ($operation === 'view') {
      if ($entity->isSiteWide() || $entity->getOwnerId() == $account->id()) {
        return AccessResult::allowed()->addCacheContexts(['user'])->addCacheTags($entity->getCacheTags());
      }
      return AccessResult::forbidden()->addCacheContexts(['user'])->addCacheTags($entity->getCacheTags());
    }

    if ($operation === 'update' || $operation === 'delete') {
      if ($entity->getOwnerId() == $account->id()) {
        return AccessResult::allowed()->addCacheContexts(['user'])->addCacheTags($entity->getCacheTags());
      }
      return AccessResult::forbidden()->addCacheContexts(['user'])->addCacheTags($entity->getCacheTags());
    }

    return parent::checkAccess($entity, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    if ($account->hasPermission('access mukurtu export')) {
      return AccessResult::allowed();
    }
    return parent::checkCreateAccess($account, $context, $entity_bundle);
  }

}
