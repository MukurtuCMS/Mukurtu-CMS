<?php

namespace Drupal\mukurtu_protocol\Entity;

use Drupal\Core\Session\AccountInterface;
use Drupal\og\OgMembershipInterface;

/**
 * Provides an interface for Mukurtu groups (protocol/community memberships).
 *
 * @ingroup mukurtu_protocol
 */
interface MukurtuGroupInterface {

  /**
   * Add a member to a group.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to add.
   * @param mixed $roles
   *   The group roles the member should be given.
   *
   * @return \Drupal\mukurtu_protocol\Entity\MukurtuGroupInterface
   *   The group.
   */
  public function addMember(AccountInterface $account, $roles = []): MukurtuGroupInterface;

  /**
   * Remove a member from a group.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to remove.
   *
   * @return \Drupal\mukurtu_protocol\Entity\MukurtuGroupInterface
   *   The group.
   */
  public function removeMember(AccountInterface $account): MukurtuGroupInterface;

  /**
   * Set roles for a member.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to add.
   * @param mixed $roles
   *   The group roles the member should be given.
   *
   * @return \Drupal\mukurtu_protocol\Entity\MukurtuGroupInterface
   *   The group.
   */
  public function setRoles(AccountInterface $account, $roles = []): MukurtuGroupInterface;

  /**
   * Returns the group membership for a given user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user to get the membership for.
   * @param array $states
   *   (optional) Array with the states to return. Defaults to only returning
   *   active memberships. In order to retrieve all memberships regardless of
   *   state, pass `OgMembershipInterface::ALL_STATES`.
   *
   * @return \Drupal\og\OgMembershipInterface|null
   *   The OgMembership entity. NULL will be returned if no membership is
   *   available that matches the passed in $states.
   */
  public function getMembership(AccountInterface $account, array $states = [OgMembershipInterface::STATE_ACTIVE]): ?OgMembershipInterface;
}
