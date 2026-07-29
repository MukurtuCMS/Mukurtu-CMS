<?php

namespace Drupal\mukurtu_local_contexts\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_protocol\Entity\MukurtuGroupInterface;

/**
 * Returns responses for Local Contexts routes.
 */
class ManageGroupSupportedProjectsController extends ControllerBase {


  /**
   * Checks access for manage group projects form.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, ?ContentEntityInterface $group = NULL, ?ContentEntityInterface $community = NULL, ?ContentEntityInterface $protocol = NULL) {
    $group = $group ?? $community ?? $protocol;
    if (!$group) {
      return AccessResult::forbidden();
    }

    // Allow uid 1 to manage LC projects no matter their roles.
    if ($account->id() == 1) {
      return AccessResult::allowed();
    }

    if ($group instanceof MukurtuGroupInterface) {
      $membership = $group->getMembership($account);
      if ($membership && ($membership->hasRole('community-community-community_manager') || $membership->hasRole('protocol-protocol-protocol_steward'))) {
        return AccessResult::allowed();
      }
    }

    return AccessResult::forbidden();
  }

  public function title(?ContentEntityInterface $group = NULL, ?ContentEntityInterface $community = NULL, ?ContentEntityInterface $protocol = NULL) {
    $group = $group ?? $community ?? $protocol;
    return $this->t("Manage Local Contexts Projects for %group", ['%group' => $group ? $group->getName() : 'Unknown Group']);
  }

}
