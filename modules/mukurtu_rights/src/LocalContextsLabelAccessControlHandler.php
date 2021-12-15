<?php

namespace Drupal\mukurtu_rights;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_rights\Entity\LocalContextsLabel;
use Drupal\og\Og;

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

    // This is our custom logic on who can "apply" a label.
    if (($entity instanceof LocalContextsLabel) && $operation == 'apply') {
      $community = $entity->getCommunity();
      if ($community) {
        // We need to check community specific access.
        $membership = Og::getMembership($community, $account);

        // Is the account a member of the community?
        if ($membership) {
          return AccessResult::allowed();
        } else {
          return AccessResult::forbidden();
        }
      }

      // Site wide label check.
      return AccessResult::allowed();
    }

    // Update and delete are system only at this point.
    return $account->id() == 1 ? AccessResult::allowed() : AccessResult::forbidden();
  }

}
