<?php

namespace Drupal\mukurtu_protocol\Entity;

use Drupal\Core\Session\AccountInterface;

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

}
