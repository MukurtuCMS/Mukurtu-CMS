<?php

namespace Drupal\mukurtu_workflows\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\og\MembershipManagerInterface;

/**
 * Route access check for the review queue page.
 *
 * Grants access to protocol stewards, language stewards, and site admins,
 * but only while the Mukurtu Editorial Workflow is the active workflow --
 * otherwise no content can ever reach a review state and the page is
 * always empty.
 * Registered as a tagged service so it can be used as a route requirement.
 */
class ReviewQueueAccessCheck implements AccessInterface {

  public function __construct(
    protected MembershipManagerInterface $membershipManager,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Checks access to the review queue route.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    $config = $this->configFactory->get('mukurtu_workflows.settings');
    if ($config->get('active_workflow') !== 'mukurtu_editorial_workflow') {
      return AccessResult::forbidden()->addCacheableDependency($config);
    }

    if ($account->hasPermission('bypass node access') || $account->hasPermission('administer nodes')) {
      return AccessResult::allowed()->cachePerPermissions()->addCacheableDependency($config);
    }

    foreach ($this->membershipManager->getMemberships($account->id()) as $membership) {
      if ($membership->getGroupEntityType() !== 'protocol') {
        continue;
      }
      if ($membership->hasRole('protocol-protocol-protocol_steward') || $membership->hasRole('protocol-protocol-language_steward')) {
        return AccessResult::allowed()
          ->cachePerUser()
          ->addCacheContexts(['og_role'])
          ->addCacheableDependency($config);
      }
    }

    return AccessResult::forbidden()
      ->cachePerUser()
      ->addCacheContexts(['og_role'])
      ->addCacheableDependency($config);
  }

}
