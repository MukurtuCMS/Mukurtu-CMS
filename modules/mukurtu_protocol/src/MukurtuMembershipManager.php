<?php

namespace Drupal\mukurtu_protocol;

//use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityInterface;
//use Drupal\node\Entity\Node;
use Drupal\og\Og;

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
    $membership = Og::createMembership($group, $account);
    $membership->save();
  }

}
