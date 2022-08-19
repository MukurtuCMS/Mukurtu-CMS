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
 * Determines access to routes based on Mukurtu roles (community/protocol).
 *
 * You can specify the '_mukurtu_role' key on route requirements. If you specify
 * a single role, users with that role with have access. If you specify multiple
 * ones you can conjunct them with AND by using a "," and with OR by using "+".
 */
class MukurtuRoleAccessCheck implements AccessInterface {

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

  /**
   * Build the list of site, community, and protocol roles for an account.
   */
  protected function getMukurtuRoles(AccountInterface $account) {
    $memberships = $this->membershipManager->getMemberships($account->id());
    $roles = [];

    // Site roles.
    $roles = array_merge($roles, $account->getRoles());

    // Community/Protocol Roles.
    foreach ($memberships as $membership) {
      $roles = array_merge($roles, $membership->getRolesIds());
    }

    return array_unique($roles);
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
    // Requirements just allow strings, so this might be a comma separated list.
    $rid_string = $route->getRequirement('_mukurtu_role');

    $explode_and = array_filter(array_map('trim', explode(',', $rid_string)));
    if (count($explode_and) > 1) {

      $diff = array_diff($explode_and, $this->getMukurtuRoles($account));
      if (empty($diff)) {
        return AccessResult::allowed()->addCacheContexts(['user.roles']);
      }
    }
    else {
      $explode_or = array_filter(array_map('trim', explode('+', $rid_string)));
      $intersection = array_intersect($explode_or, $this->getMukurtuRoles($account));
      if (!empty($intersection)) {
        return AccessResult::allowed()->addCacheContexts(['user.roles']);
      }
    }

    // If there is no allowed role, give other access checks a chance.
    return AccessResult::neutral()->addCacheContexts(['user.roles']);
  }

}
