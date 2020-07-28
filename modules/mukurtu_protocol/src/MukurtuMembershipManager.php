<?php

namespace Drupal\mukurtu_protocol;

//use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityInterface;
//use Drupal\node\Entity\Node;
use Drupal\og\Og;
use Drupal\og\OgMembershipInterface;

/**
 * Provides a service for managing memberships in Communities and Protocols.
 */
class MukurtuMembershipManager {

  /**
   * Load the protocol lookup table.
   */
  public function __construct() {
  }

  /**
   * Add a user to a group.
   *
   * @param \Drupal\Core\Entity\EntityInterface $group
   *   The group node.
   * @param Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function addMember(EntityInterface $group, AccountInterface $account) {
    // Is the account already a member of the group?
    $membership = Og::getMembership($group, $account, OgMembershipInterface::ALL_STATES);
    if (!$membership) {
      $membership = Og::createMembership($group, $account);
      $membership->save();
    }
  }

  /**
   * Remove a user from a group.
   *
   * @param \Drupal\Core\Entity\EntityInterface $group
   *   The group node.
   * @param Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function removeMember(EntityInterface $group, AccountInterface $account) {
    $membership = Og::getMembership($group, $account, OgMembershipInterface::ALL_STATES);
    $membership->delete();
  }

}
