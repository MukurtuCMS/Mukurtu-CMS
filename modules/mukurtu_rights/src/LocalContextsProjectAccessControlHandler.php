<?php

namespace Drupal\mukurtu_rights;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the Local Contexts Project Entity.
 *
 * @see \Drupal\mukurtu_rights\Entity\LocalContextsProject
 */
class LocalContextsProjectAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Projects are system only at this point.
    // Let UID 1 interact with them only.
    return $account->id() == 1 ? AccessResult::allowed() : AccessResult::forbidden();
  }

}
