<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Drupal\node\NodeInterface;
use Drupal\og\Og;
use Drupal\user\Entity\User;
use Drupal\og\OgMembershipInterface;
use Drupal\Core\Link;

class MukurtuManageUserMembershipsController extends ControllerBase {

  /**
   * Check access for managing a specific user's memberships.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\user\UserInterface $user
   *   The user to manage memberships for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, UserInterface $user) {
    $memberships = Og::getMemberships($account);

    // User needs permission to manage membership for at least one protocol
    // or community.
    foreach ($memberships as $membership) {
      if (!in_array($membership->getGroupBundle(), ['protocol', 'community'])) {
        continue;
      }

      // Found one group where they have sufficent access, no need to check
      // the rest.
      if ($membership->hasPermission('administer group') || $membership->hasPermission('manage members')) {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }

  public function content(UserInterface $user) {
    $build = [];

    $build['memberships'] = \Drupal::formBuilder()->getForm('Drupal\mukurtu_protocol\Form\ManageUserMembershipForm', $user);

    return $build;
  }
}
