<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Session\AccountInterface;

interface MukurtuPermissionsCheckInterface {
  public function hasMukurtuPermissions(AccountInterface $account, array $permissions, $conjunction = 'AND');

  /**
   * Checks whether a user has a certain permission.
   *
   * @param string $permission
   *   The permission string to check.
   *
   * @return bool
   *   TRUE if the user has the permission, FALSE otherwise.
   */
  public function hasPermission(AccountInterface $account, $permission);

  /**
   * Checks whether a user has certain permissions.
   *
   * @param array $permissions
   *   The permission strings to check.
   *
   * @param string $conjunction
   *   'AND' if user requires all permissions, 'OR' if checking for only one.
   *
   * @return bool
   *   TRUE if the user has permission, FALSE otherwise.
   */
  public function hasPermissions(AccountInterface $account, array $permissions, $conjunction = 'AND');
}
