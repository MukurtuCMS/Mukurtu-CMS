<?php

namespace Drupal\mukurtu_rights;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the Local Contexts Label Entity.
 *
 * @see \Drupal\mukurtu_rights\Entity\LocalContextsLabel
 */
class LocalContextsLabelAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation == 'view') {
      return AccessResult::allowed();
    }

    // Update and delete are system only at this point.
    return $account->id() == 1 ? AccessResult::allowed() : AccessResult::forbidden();
  }

}
