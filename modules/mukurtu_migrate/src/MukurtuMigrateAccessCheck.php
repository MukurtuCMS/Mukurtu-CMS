<?php

namespace Drupal\mukurtu_migrate;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Session\AccountInterface;

/**
 * Checks access for mukurtu_migrate routes.
 *
 * The Mukurtu migration can only be used by user 1. This is because any other
 * user might have different permissions on the source and target site.
 *
 * This class is designed to be used with '_custom_access' route requirement.
 *
 * @see \Drupal\Core\Access\CustomAccessCheck
 */
class MukurtuMigrateAccessCheck {

  /**
   * Checks if the user is user 1 and grants access if so.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkAccess(AccountInterface $account) {
    // The access result is uncacheable because it is just limiting access to
    // the migrate UI which is not worth caching.
    return AccessResultAllowed::allowedIf((int) $account->id() === 1)->mergeCacheMaxAge(0);
  }

  /**
   * Checks if the user is user 1 and grants access if so.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkAccessResults(AccountInterface $account) {
    // The access result is uncacheable because it is just limiting access to
    // the migrate UI which is not worth caching.
    return AccessResultAllowed::allowedIf((int) $account->id() === 1)->mergeCacheMaxAge(0);
  }

}
