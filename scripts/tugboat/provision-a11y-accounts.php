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
 * Returns the community the test accounts belong to, creating one if needed.
 *
 * Reuses an existing community rather than adding one. On a freshly
 * installed preview there are none yet (default content is seeded later by
 * default-content.spec.ts), so one gets created - but on any site that
 * already has communities this joins the first, rather than cluttering the
 * /communities listing that the scans themselves discover pages from.
 */
function _a11y_community(): Community {
  $ids = \Drupal::entityTypeManager()->getStorage('community')->getQuery()
    ->accessCheck(FALSE)->sort('id')->range(0, 1)->execute();

  if ($ids) {
    return Community::load(reset($ids));
  }

  $community = Community::create(['name' => 'Accessibility Testing Community']);
  $community->save();
  \Drupal::messenger()->addStatus('Created a community for the accessibility accounts.');
  return $community;
}

/**
 * Returns a protocol in that community, creating one if needed.
 */
function _a11y_protocol(Community $community): Protocol {
  $ids = \Drupal::entityTypeManager()->getStorage('protocol')->getQuery()
    ->accessCheck(FALSE)
    ->condition('field_communities', $community->id())
    ->sort('id')->range(0, 1)->execute();

  if ($ids) {
    return Protocol::load(reset($ids));
  }

  $protocol = Protocol::create([
    'name' => 'Accessibility Testing Protocol',
    'field_communities' => [$community->id()],
    'field_access_mode' => 'strict',
  ]);
  $protocol->save();
  \Drupal::messenger()->addStatus('Created a protocol for the accessibility accounts.');
  return $protocol;
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
  $member_community = _a11y_community();
  _a11y_membership($member_community, $member, ['community_member']);
  _a11y_membership(_a11y_protocol($member_community), $member, ['protocol_member']);
  \Drupal::messenger()->addStatus("Accessibility member account '$member_name' is ready: member of '{$member_community->label()}'.");
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

$community = _a11y_community();
$protocol = _a11y_protocol($community);

_a11y_membership($community, $manager, ['community_manager']);
_a11y_membership($protocol, $manager, ['protocol_steward']);

\Drupal::messenger()->addStatus("Accessibility manage-adjacent account '$manager_name' is ready: community_manager on '{$community->label()}', protocol_steward on '{$protocol->label()}'.");
