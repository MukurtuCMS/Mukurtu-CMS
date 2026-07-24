<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Session\AccountInterface;
use Drupal\og\MembershipManagerInterface;

/**
 * Determines whether one user manages an OG group another user belongs to.
 */
class OgManagerAccessChecker {

  /**
   * OG group bundles a "manage members" permission is checked against.
   */
  const MANAGED_BUNDLES = ['community', 'protocol'];

  public function __construct(protected MembershipManagerInterface $membershipManager) {
  }

  /**
   * Checks whether $manager manages a community/protocol $target belongs to.
   *
   * @param \Drupal\Core\Session\AccountInterface $manager
   *   The account to check for "manage members" permission.
   * @param \Drupal\Core\Session\AccountInterface $target
   *   The account being viewed or managed.
   *
   * @return bool
   *   TRUE if $manager has 'manage members' in a community or protocol that
   *   $target also belongs to.
   */
  public function sharesManagedGroup(AccountInterface $manager, AccountInterface $target): bool {
    $managed_group_ids = $this->getManagedGroupIds($manager);

    if (!$managed_group_ids) {
      return FALSE;
    }

    foreach ($this->membershipManager->getMemberships($target->id()) as $membership) {
      $bundle = $membership->getGroupBundle();
      if (!empty($managed_group_ids[$bundle]) && in_array($membership->getGroupId(), $managed_group_ids[$bundle], TRUE)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Checks whether $account has 'manage members' in any community/protocol.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check for "manage members" permission.
   *
   * @return bool
   *   TRUE if $account has 'manage members' in at least one community or
   *   protocol group.
   */
  public function managesAnyGroup(AccountInterface $account): bool {
    return (bool) $this->getManagedGroupIds($account);
  }

  /**
   * Builds a map of community/protocol group ids $account manages.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check for "manage members" permission.
   *
   * @return array
   *   An array keyed by group bundle, of arrays of group ids $account has
   *   'manage members' permission in.
   */
  protected function getManagedGroupIds(AccountInterface $account): array {
    $managed_group_ids = [];
    foreach ($this->membershipManager->getMemberships($account->id()) as $membership) {
      $bundle = $membership->getGroupBundle();
      if (in_array($bundle, self::MANAGED_BUNDLES, TRUE) && $membership->hasPermission('manage members')) {
        $managed_group_ids[$bundle][] = $membership->getGroupId();
      }
    }
    return $managed_group_ids;
  }

}
