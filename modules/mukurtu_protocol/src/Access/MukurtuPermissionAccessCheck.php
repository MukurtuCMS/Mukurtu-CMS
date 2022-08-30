<?php

namespace Drupal\mukurtu_protocol\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;
use Drupal\og\OgRoleManagerInterface;
use Drupal\og\MembershipManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Determines access to routes based on community/protocol permissions.
 */
class MukurtuPermissionAccessCheck implements AccessInterface {

  /**
   * The OG role manager.
   *
   * @var \Drupal\og\OgRoleManagerInterface
   */
  protected $roleManager;

  /**
   * The OG group membership manager.
   *
   * @var \Drupal\og\MembershipManagerInterface
   */
  protected $membershipManager;

  public function __construct(OgRoleManagerInterface $role_manager, MembershipManagerInterface $membership_manager) {
    $this->roleManager = $role_manager;
    $this->membershipManager = $membership_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('og.role_manager'),
      $container->get('og.membership_manager')
    );
  }

  protected function hasMukurtuPermissions(AccountInterface $account, $permissions, $conjunction = 'AND') {
    // Get the account's community and protocol memberships.
    $foundPermissions = [];
    $memberships = $this->membershipManager->getMemberships($account->id());
    foreach ($permissions as $permission) {
      // Site permissions are indicated by a 'site:' prefix.
      if (substr_compare($permission, 'site:', 0, 5) == 0) {
        $sitePermission = substr($permission, 5);
        if ($account->hasPermission($sitePermission)) {
          if ($conjunction == 'OR') {
            return AccessResult::allowed();
          }
          $foundPermissions[] = $permission;
          continue;
        }
        // Failed the check due to an AND conjunction.
        return AccessResult::forbidden();
      }

      // Check OG memberships for OG level permissions.
      foreach ($memberships as $membership) {
        if ($membership->hasPermission($permission)) {
          if ($conjunction == 'OR') {
            return AccessResult::allowed();
          }
          $foundPermissions[] = $permission;
          break;
        }
      }
    }

    return count($foundPermissions) == count($permissions) ? AccessResult::allowed() : AccessResult::neutral();
  }

  /**
   * Checks access.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account) {
    $permission = $route->getRequirement('_mukurtu_permission');

    if ($permission === NULL) {
      return AccessResult::neutral();
    }

    // Allow to conjunct the permissions with OR ('+') or AND (',').
    $split = explode(',', $permission);
    if (count($split) > 1) {
      return $this->hasMukurtuPermissions($account, $split, 'AND');
    }
    else {
      $split = explode('+', $permission);
      return $this->hasMukurtuPermissions($account, $split, 'OR');
    }
  }

}
