<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\mukurtu_protocol\Entity\MukurtuGroupInterface;

/**
 * Controller for controlling access to og member management pages.
 */
class MukurtuOgMembersOverviewController extends ControllerBase
{
  /**
   * Access check for OG membership pages.
   */
  public function access(AccountInterface $account, MukurtuGroupInterface $group)
  {
    // Allow uid 1 to view og membership pages no matter their roles.
    if ($account->id() == 1) {
      return AccessResult::allowed();
    }
    $membership = $group->getMembership($account);
    if ($membership && ($membership->hasRole("community-community-community_manager") || $membership->hasRole("protocol-protocol-protocol_steward"))) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }
}
