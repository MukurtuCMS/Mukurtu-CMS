<?php

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how the V3 -> V4 user migrations map Drupal 7 roles.
 *
 * The 'roles' pipeline maps each D7 rid through static_map one at a time
 * (the source value is an array and static_map doesn't handle multiples),
 * and every rid it doesn't know maps to the default, 'authenticated'. A D7
 * user with two or more unmapped roles therefore used to arrive at
 * User::preSave() with 'authenticated' listed twice, and its by-index
 * stripping of that role threw "Unable to remove item at non-existing index",
 * silently skipping the account. The array_unique step added to both user
 * migrations collapses those duplicates first.
 *
 * @see \Drupal\mukurtu_migrate\Plugin\migrate\process\ArrayUnique
 * @see \Drupal\user\Entity\User::preSave()
 */
#[Group('mukurtu_migrate')]
class MukurtuCmsV3UsersRolesTest extends MukurtuCmsV3UsersMigrationTestBase {

  /**
   * A D7 rid the migration maps to mukurtu_manager.
   */
  const MAPPED_RID = 4;

  /**
   * A second D7 rid the migration also maps to mukurtu_manager.
   */
  const OTHER_MAPPED_RID = 6;

  /**
   * A D7 rid the migration doesn't know, e.g. "administrator".
   */
  const UNMAPPED_RID = 3;

  /**
   * Another D7 rid the migration doesn't know, e.g. "contributor".
   */
  const OTHER_UNMAPPED_RID = 5;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The only non-locked role the shipped 'roles' map points at.
    Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager'])->save();

    $this->addSourceUser(10, 'Two Unmapped Roles', [static::UNMAPPED_RID, static::OTHER_UNMAPPED_RID]);
    $this->addSourceUser(11, 'Two Mapped Roles', [static::MAPPED_RID, static::OTHER_MAPPED_RID]);
    $this->addSourceUser(12, 'Mixed Roles', [static::UNMAPPED_RID, static::MAPPED_RID]);
    $this->addSourceUser(13, 'One Unmapped Role', [static::UNMAPPED_RID]);
    $this->addSourceUser(14, 'No Roles');

    $this->installMigrationConfig('mukurtu_cms_v3_users');
  }

  /**
   * Returns the non-locked roles stored for a migrated user.
   *
   * @param int $uid
   *   The destination uid.
   *
   * @return string[]
   *   The role ids as stored, duplicates included.
   */
  protected function storedRoles(int $uid): array {
    $user = User::load($uid);
    $this->assertNotNull($user, "Source user $uid was not migrated.");
    return $user->getRoles(TRUE);
  }

  /**
   * Tests that a user with several unmapped D7 roles is migrated.
   *
   * This is the case that used to fail: both rids map to 'authenticated'.
   */
  public function testUserWithSeveralUnmappedRolesIsMigrated(): void {
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users');

    $user = User::load(10);
    $this->assertNotNull($user, 'The user with two unmapped roles was skipped.');
    $this->assertSame('Two Unmapped Roles', $user->getAccountName());
    // 'authenticated' is implicit and never stored.
    $this->assertSame([], $this->storedRoles(10));
  }

  /**
   * Tests that a role reached through several D7 rids is stored once.
   */
  public function testMappedRoleIsNotDuplicated(): void {
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users');

    $this->assertSame(['mukurtu_manager'], $this->storedRoles(11));
  }

  /**
   * Tests the cases that already worked keep working.
   */
  public function testOtherRoleCombinationsAreUnaffected(): void {
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users');

    $this->assertSame(['mukurtu_manager'], $this->storedRoles(12));
    $this->assertSame([], $this->storedRoles(13));
    $this->assertSame([], $this->storedRoles(14));
  }

  /**
   * Tests that the uid 1 migration ignores the source roles entirely.
   *
   * It no longer maps roles at all, so a source user 1 with any number of
   * unmapped roles is migrated without touching this site's administrator.
   *
   * @see \Drupal\Tests\mukurtu_migrate\Kernel\MukurtuCmsV3UsersAccountFieldsTest::testAdminKeepsItsOwnRoles()
   */
  public function testAdminUserWithSeveralUnmappedRolesIsMigrated(): void {
    // The uid 1 migration updates the site's existing admin account rather
    // than creating one, so it must already exist at the destination.
    $admin = User::create([
      'uid' => 1,
      'name' => 'admin',
      'mail' => 'admin@example.com',
      'status' => 1,
      'roles' => ['mukurtu_manager'],
    ]);
    $admin->enforceIsNew();
    $admin->save();

    $this->addSourceUser(1, 'Legacy Admin', [static::UNMAPPED_RID, static::OTHER_UNMAPPED_RID]);
    $this->installMigrationConfig('mukurtu_cms_v3_users_uid1');

    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users_uid1');

    $this->assertSame(['mukurtu_manager'], $this->storedRoles(1), 'The uid 1 migration changed this site\'s administrator roles.');
    $this->assertSame(1000000000, (int) User::load(1)->getCreatedTime(), 'The uid 1 row was not actually processed.');
  }

}
