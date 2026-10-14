<?php

/**
 * @file
 * Provisions the accounts the accessibility scans log in as.
 *
 * Run with `drush php:script` from the Tugboat build. Does nothing at all
 * unless the corresponding environment variables are set, so a preview with
 * no accessibility credentials configured behaves exactly as before.
 *
 * Why this exists: the Tugboat preview runs `drush site-install mukurtu` on
 * every build, producing a site whose only account is `admin`. Without
 * these accounts, setting the A11Y_* GitHub secrets makes every member and
 * manage-adjacent scan fail to log in - each one burning the 30s
 * waitForURL timeout in Login.login() - rather than making the scans more
 * representative. See issue #2241.
 *
 * Why a script rather than a few drush commands in config.yml: the
 * manage-adjacent account is not just a user with a role. Mukurtu resolves
 * those routes through OG group memberships (MukurtuRoleAccessCheck and
 * MukurtuPermissionAccessCheck union site roles with group roles), so the
 * account needs real memberships on a real community and protocol.
 */

use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\og\OgMembershipInterface;
use Drupal\user\Entity\User;

/**
 * Reads an environment variable, treating empty as unset.
 *
 * Tugboat, like GitHub Actions, hands through an unset variable as an empty
 * string rather than omitting it.
 */
function _a11y_env(string $name): ?string {
  $value = getenv($name);
  return ($value === FALSE || trim($value) === '') ? NULL : $value;
}

/**
 * Creates a user, or resets its password if it already exists.
 *
 * Idempotent: Tugboat rebuilds and refreshes re-run this, and the password
 * may have been rotated in the meantime.
 */
function _a11y_user(string $username, string $password): User {
  $existing = \Drupal::entityTypeManager()
    ->getStorage('user')
    ->loadByProperties(['name' => $username]);

  $account = reset($existing);
  if (!$account) {
    $account = User::create(['name' => $username]);
    \Drupal::messenger()->addStatus("Created user '$username'.");
  }

  $account->setPassword($password);
  $account->setEmail($username . '@example.com');
  $account->activate();
  $account->save();

  return $account;
}

/**
 * Ensures a membership exists AND carries the given roles.
 *
 * Community::addMember()/Protocol::addMember() return early when a
 * membership already exists, so they never repair the roles on a rebuild.
 * This sets them either way.
 */
function _a11y_membership($group, User $account, array $role_names): void {
  $group_type = $group->getEntityTypeId();
  $membership = Og::getMembership($group, $account, OgMembershipInterface::ALL_STATES);

  if (!$membership) {
    $membership = Og::createMembership($group, $account);
  }

  $roles = [];
  foreach ($role_names as $role_name) {
    $role = OgRole::getRole($group_type, $group_type, $role_name);
    if (!$role) {
      \Drupal::messenger()->addWarning("OG role '$role_name' does not exist on $group_type; skipping it.");
      continue;
    }
    $roles[] = $role;
  }

  $membership->setRoles($roles);
  $membership->setState(OgMembershipInterface::STATE_ACTIVE);
  $membership->save();
}

/**
 * Returns an existing community for the test accounts, or NULL.
 *
 * Deliberately does not create one. On the Tugboat preview this script runs
 * straight after drush site-install, when no content exists at all, so an
 * earlier version created its own "Accessibility Testing Community" here.
 * The scans then discovered that synthetic group rather than the seeded
 * one: its Local Contexts pages 404, which is how
 * manage-community-local-contexts-projects came to skip with "returned
 * HTTP 404" instead of scanning anything, and the member never held
 * membership in any seeded protocol so it could not reach gated content.
 *
 * The seeded groups are created later, by default-content.spec.ts, which
 * grants these accounts their memberships at that point. This function
 * still finds a group when one already exists, which is the case on a
 * developer's local site where content was seeded before provisioning ran.
 *
 * See issue #2250.
 */
function _a11y_community(): ?Community {
  $ids = \Drupal::entityTypeManager()->getStorage('community')->getQuery()
    ->accessCheck(FALSE)->sort('id')->range(0, 1)->execute();

  return $ids ? Community::load(reset($ids)) : NULL;
}

/**
 * Returns an existing protocol in that community, or NULL. Creates nothing.
 */
function _a11y_protocol(Community $community): ?Protocol {
  $ids = \Drupal::entityTypeManager()->getStorage('protocol')->getQuery()
    ->accessCheck(FALSE)
    ->condition('field_communities', $community->id())
    ->sort('id')->range(0, 1)->execute();

  return $ids ? Protocol::load(reset($ids)) : NULL;
}

/**
 * Grants an account its group roles, when the groups exist yet.
 */
function _a11y_enrol(\Drupal\user\Entity\User $account, string $community_role, string $protocol_role, string $label): void {
  $community = _a11y_community();
  if (!$community) {
    \Drupal::messenger()->addStatus("No groups exist yet, so $label was created without memberships. default-content.spec.ts grants them once the seeded groups exist.");
    return;
  }

  _a11y_membership($community, $account, [$community_role]);
  if ($protocol = _a11y_protocol($community)) {
    _a11y_membership($protocol, $account, [$protocol_role]);
  }
  \Drupal::messenger()->addStatus("$label enrolled in '{$community->label()}'.");
}

// -----------------------------------------------------------------------
// 1. The member account.
//
// An ordinary authenticated user with no site roles beyond `authenticated`,
// but a plain member of a community and a protocol. The memberships matter:
// page-inventory.md asks for "a regular community/protocol member account"
// precisely because on a protocol-heavy site only members can reach the
// gated item pages, and a member with no memberships simply skips those
// scans. Verified against a local site: without them, one more page skips
// than admin reaches.
// -----------------------------------------------------------------------
$member_name = _a11y_env('A11Y_USERNAME');
$member_pass = _a11y_env('A11Y_PASSWORD');

if ($member_name && $member_pass) {
  $member = _a11y_user($member_name, $member_pass);
  _a11y_enrol($member, 'community_member', 'protocol_member', "Accessibility member account '$member_name'");
}
else {
  \Drupal::messenger()->addStatus('A11Y_USERNAME/A11Y_PASSWORD not set; skipping the member account. Member scans will run as admin.');
}

// -----------------------------------------------------------------------
// 2. The manage-adjacent account.
//
// This needs two group memberships to reach everything in the
// manage-adjacent inventory:
//
//   - community_manager on a community, which carries 'manage members' and
//     so opens /admin/people/list and /admin/communities/create-user (both
//     require the OG permission community:manage members).
//   - protocol_steward on a protocol, which is one of the roles
//     mukurtu_core's RouteSubscriber accepts for /admin/content, and which
//     ManageGroupSupportedProjectsController accepts for the Local Contexts
//     projects pages.
// -----------------------------------------------------------------------
$manager_name = _a11y_env('A11Y_MANAGER_USERNAME');
$manager_pass = _a11y_env('A11Y_MANAGER_PASSWORD');

if (!$manager_name || !$manager_pass) {
  \Drupal::messenger()->addStatus('A11Y_MANAGER_USERNAME/A11Y_MANAGER_PASSWORD not set; skipping the manage-adjacent account. Those scans will run as admin.');
  return;
}

$manager = _a11y_user($manager_name, $manager_pass);
_a11y_enrol($manager, 'community_manager', 'protocol_steward', "Accessibility manage-adjacent account '$manager_name'");
