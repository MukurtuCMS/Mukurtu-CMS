<?php

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the account fields the V3 -> V4 user migrations are meant to carry.
 *
 * These assertions are about the shipped migration configs, independent of
 * which destination plugin class ends up handling entity:user. The
 * subclass re-runs every one of them with mukurtu_import installed, which
 * is where that distinction has gone wrong before.
 *
 * @see \Drupal\Tests\mukurtu_migrate\Kernel\MukurtuCmsV3UsersAccountFieldsWithImportTest
 * @see modules/mukurtu_migrate/README.md
 */
#[Group('mukurtu_migrate')]
class MukurtuCmsV3UsersAccountFieldsTest extends MukurtuCmsV3UsersMigrationTestBase {

  /**
   * The D7 created time every fixture user carries.
   */
  const SOURCE_CREATED = 1000000000;

  /**
   * The D7 last access/login time every fixture user carries.
   */
  const SOURCE_ACCESS = 1000000001;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->addSourceUser(20, 'Active Person', [2]);
    $this->addSourceUser(21, 'Blocked Person', [2], 0);

    $this->installMigrationConfig('mukurtu_cms_v3_users');
  }

  /**
   * Tests that a blocked D7 account is still blocked after migrating.
   */
  public function testBlockedUserStaysBlocked(): void {
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users');

    $this->assertTrue(User::load(20)->isActive(), 'An active source user did not stay active.');
    $this->assertTrue(User::load(21)->isBlocked(), 'A blocked source user was activated by the migration.');
  }

  /**
   * Tests that the D7 password hash is carried over, not discarded.
   *
   * Migrated users still have to reset their password, because Drupal 11
   * can't verify a D7 hash. That's a property of the hash format, not a
   * reason for the migration to drop the column: an empty pass would also
   * wipe the only record of the old credential.
   */
  public function testPasswordHashIsMigrated(): void {
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users');

    $this->assertNotEmpty(User::load(20)->getPassword(), 'The source password hash was dropped.');
  }

  /**
   * Tests the timestamps and timezone the migration maps.
   */
  public function testAccountMetadataIsMigrated(): void {
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users');

    $user = User::load(20);
    $this->assertSame(static::SOURCE_CREATED, (int) $user->getCreatedTime());
    $this->assertSame(static::SOURCE_ACCESS, (int) $user->getLastAccessedTime());
    $this->assertSame(static::SOURCE_ACCESS, (int) $user->getLastLoginTime());
    $this->assertSame('America/New_York', $user->getTimeZone());
  }

  /**
   * The name this site's own administrator account carries.
   */
  const ADMIN_NAME = 'site_admin';

  /**
   * The two shapes a Mukurtu 3 user 1 can have relative to this site's.
   *
   * A migration where both sites' user 1 happen to share a name looks like
   * a no-op row, which is exactly why the roles and status overwrites went
   * unnoticed. Both cases have to behave the same way.
   */
  public static function sourceAdminNames(): array {
    return [
      'source user 1 has a different name' => ['Legacy Admin'],
      'source user 1 has the same name' => [self::ADMIN_NAME],
    ];
  }

  /**
   * Creates this site's own administrator account at uid 1.
   */
  protected function createSiteAdmin(int $status = 1): void {
    Role::create(['id' => 'administrator', 'label' => 'Administrator'])->save();
    Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager'])->save();

    $admin = User::create([
      'uid' => 1,
      'name' => static::ADMIN_NAME,
      'mail' => 'site-admin@example.com',
      'status' => $status,
      'timezone' => 'UTC',
      'roles' => ['administrator'],
    ]);
    $admin->enforceIsNew();
    $admin->save();
  }

  /**
   * Tests that uid 1 is updated in place by its own migration.
   *
   * The site's existing admin account keeps its name, password and email
   * (the uid1 migration deliberately maps none of those), but must take on
   * the source site's own timestamps and timezone.
   *
   * @see modules/mukurtu_migrate/config/install/migrate_plus.migration.mukurtu_cms_v3_users_uid1.yml
   */
  #[DataProvider('sourceAdminNames')]
  public function testAdminAccountIsUpdatedInPlace(string $source_name): void {
    $this->createSiteAdmin();

    $this->addSourceUser(1, $source_name, [2]);
    $this->installMigrationConfig('mukurtu_cms_v3_users_uid1');
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users_uid1');

    $admin = User::load(1);
    $this->assertNotNull($admin, 'The admin account disappeared.');
    $this->assertSame(static::SOURCE_CREATED, (int) $admin->getCreatedTime(), 'The uid 1 row was never applied.');
    $this->assertSame('America/New_York', $admin->getTimeZone());

    // Name, password and email are deliberately not migrated for uid 1, so
    // the site's own admin credentials keep working.
    $this->assertSame(static::ADMIN_NAME, $admin->getAccountName());
    $this->assertSame('site-admin@example.com', $admin->getEmail());
  }

  /**
   * Tests that this site's administrator keeps its own roles.
   *
   * The migrate destination overwrites a mapped field wholesale, so mapping
   * roles here replaced the roles of an account that already exists. A
   * Mukurtu 3 user 1 carrying only the plain authenticated rid mapped to
   * 'authenticated', which core strips as implicit, so this site's
   * administrator was left with no roles at all.
   */
  #[DataProvider('sourceAdminNames')]
  public function testAdminKeepsItsOwnRoles(string $source_name): void {
    $this->createSiteAdmin();
    $this->assertSame(['administrator'], User::load(1)->getRoles(TRUE), 'Precondition: the site admin should start with the administrator role.');

    $this->addSourceUser(1, $source_name, [2]);
    $this->installMigrationConfig('mukurtu_cms_v3_users_uid1');
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users_uid1');

    $this->assertSame(['administrator'], User::load(1)->getRoles(TRUE), 'The migration stripped this site\'s administrator role.');
  }

  /**
   * Tests that a blocked Mukurtu 3 user 1 doesn't block this site's admin.
   *
   * Mapping status here locked the operator out of the site they were
   * migrating into.
   */
  #[DataProvider('sourceAdminNames')]
  public function testAdminIsNotBlockedByTheSourceSite(string $source_name): void {
    $this->createSiteAdmin();

    // The Mukurtu 3 site's own user 1 was blocked.
    $this->addSourceUser(1, $source_name, [2], 0);
    $this->installMigrationConfig('mukurtu_cms_v3_users_uid1');
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users_uid1');

    $this->assertTrue(User::load(1)->isActive(), 'The migration blocked this site\'s administrator account.');
  }

}
