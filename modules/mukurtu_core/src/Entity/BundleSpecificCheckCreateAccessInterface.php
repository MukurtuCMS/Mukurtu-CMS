<?php

namespace Drupal\mukurtu_core\Entity;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

interface BundleSpecificCheckCreateAccessInterface {
  /**
   * Bundle specific create access check to be called from the
   * standard checkCreateAccess method of the AccessControlHandler.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account being checked against.
   * @param array $context
   *   Context.
   * @return \Drupal\Core\Access\AccessResult
   *   The result.
   */
  public static function bundleCheckCreateAccess(AccountInterface $account, array $context): AccessResult;
}
