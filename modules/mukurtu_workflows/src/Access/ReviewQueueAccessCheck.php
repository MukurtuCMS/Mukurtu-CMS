<?php

namespace Drupal\mukurtu_workflows\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\og\MembershipManagerInterface;

/**
 * Route access check for the review queue page.
 *
 * Grants access to protocol stewards, language stewards, and site admins.
 * Registered as a tagged service so it can be used as a route requirement.
 */
class ReviewQueueAccessCheck implements AccessInterface {

  public function __construct(protected MembershipManagerInterface $membershipManager) {}

  /**
   * Checks access to the review queue route.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    if ($account->hasPermission('bypass node access') || $account->hasPermission('administer nodes')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    foreach ($this->membershipManager->getMemberships($account->id()) as $membership) {
      if ($membership->getGroupEntityType() !== 'protocol') {
        continue;
      }
      if ($membership->hasRole('protocol-protocol-protocol_steward') || $membership->hasRole('protocol-protocol-language_steward')) {
        return AccessResult::allowed()
          ->cachePerUser()
          ->addCacheContexts(['og_role']);
      }
    }

    return AccessResult::forbidden()
      ->cachePerUser()
      ->addCacheContexts(['og_role']);
  }

}
