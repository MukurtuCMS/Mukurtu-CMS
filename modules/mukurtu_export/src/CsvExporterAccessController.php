<?php

namespace Drupal\mukurtu_export;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

class CsvExporterAccessController extends EntityAccessControlHandler {
  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

     /** @var \Drupal\mukurtu_export\Entity\CsvExporter $entity */
    if ($operation == 'view') {
      if ($entity->isSiteWide() || $entity->getOwnerId() == $account->id()) {
        return AccessResult::allowed();
      }
      return AccessResult::forbidden();
    }

    if ($operation == 'delete' || $operation == 'update') {
      if ($entity->getOwnerId() == $account->id()) {
        return AccessResult::allowed();
      }
      return AccessResult::forbidden();
    }

    return parent::checkAccess($entity, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // Users who can export should be able to create their own export settings.
    if ($account->hasPermission('access mukurtu export')) {
      return AccessResult::allowed();
    }

    return parent::checkCreateAccess($account, $context, $entity_bundle);
  }

}
