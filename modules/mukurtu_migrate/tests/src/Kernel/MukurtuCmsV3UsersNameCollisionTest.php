<?php

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests migrating a Mukurtu 3 user whose username is already in use here.
 *
 * Drupal keeps a unique index on the username, so such a row used to fail
 * with "Integrity constraint violation: Duplicate entry ... for key
 * user__name" and the account was never created, leaving that user's
 * Mukurtu 3 content with no owner to be attributed to.
 *
 * @see \Drupal\mukurtu_migrate\Plugin\migrate\process\AvoidUsernameCollision
 */
#[Group('mukurtu_migrate')]
class MukurtuCmsV3UsersNameCollisionTest extends MukurtuCmsV3UsersMigrationTestBase {

  /**
   * The name shared by this site's account and a Mukurtu 3 user.
   */
  const SHARED_NAME = 'site_admin';

  /**
   * The source uid of the Mukurtu 3 user holding the shared name.
   */
  const COLLIDING_SOURCE_UID = 7;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // This site's own account, created before the migration runs.
    $existing = User::create([
      'uid' => 4,
      'name' => static::SHARED_NAME,
      'mail' => 'site-admin@example.com',
      'status' => 1,
    ]);
    $existing->enforceIsNew();
    $existing->save();

    $this->addSourceUser(static::COLLIDING_SOURCE_UID, static::SHARED_NAME, [2]);
    $this->addSourceUser(8, 'Unique Person', [2]);

    $this->installMigrationConfig('mukurtu_cms_v3_users');
  }

  /**
   * Returns the account the colliding source row was migrated to.
   */
  protected function migratedCollidingUser(): UserInterface {
    $id_map = $this->getMigration('mukurtu_cms_v3_users')->getIdMap();
    $destination_ids = $id_map->lookupDestinationIds(['uid' => static::COLLIDING_SOURCE_UID]);
    $this->assertNotEmpty($destination_ids, 'The colliding source user was not migrated at all.');
    $user = User::load(reset($destination_ids[0]));
    $this->assertNotNull($user);
    return $user;
  }

  /**
   * Tests that the colliding user is migrated under a numbered name.
   */
  public function testCollidingUserIsRenamed(): void {
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users');

    // This site's own account is untouched.
    $existing = User::load(4);
    $this->assertSame(static::SHARED_NAME, $existing->getAccountName());
    $this->assertSame('site-admin@example.com', $existing->getEmail());

    // The Mukurtu 3 user exists, renamed, with its own details intact.
    $migrated = $this->migratedCollidingUser();
    $this->assertSame(static::SHARED_NAME . '_1', $migrated->getAccountName());
    $this->assertSame('site_admin@example.com', $migrated->getEmail());
    $this->assertNotSame(4, (int) $migrated->id(), 'The migration overwrote this site\'s own account.');

    // A source user with no collision keeps its name.
    $this->assertSame('Unique Person', User::load(8)->getAccountName());
  }

  /**
   * Tests that a second collision gets the next free number.
   */
  public function testSecondCollisionGetsTheNextNumber(): void {
    $taken = User::create([
      'uid' => 5,
      'name' => static::SHARED_NAME . '_1',
      'mail' => 'taken@example.com',
      'status' => 1,
    ]);
    $taken->enforceIsNew();
    $taken->save();

    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users');

    $this->assertSame(static::SHARED_NAME . '_2', $this->migratedCollidingUser()->getAccountName());
  }

  /**
   * Tests that re-running with --update doesn't rename the user again.
   */
  public function testUpdateRunKeepsTheRenamedAccount(): void {
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users');
    $migrated_uid = (int) $this->migratedCollidingUser()->id();
    $this->assertSame(static::SHARED_NAME . '_1', User::load($migrated_uid)->getAccountName());

    $migration = $this->getMigration('mukurtu_cms_v3_users');
    $migration->getIdMap()->prepareUpdate();
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users');

    $this->assertSame($migrated_uid, (int) $this->migratedCollidingUser()->id(), 'The update run moved the renamed user to another account.');
    $this->assertSame(static::SHARED_NAME . '_1', User::load($migrated_uid)->getAccountName(), 'The update run renamed the user a second time.');
  }

  /**
   * Tests that a name at Drupal's length limit still fits its suffix.
   */
  public function testLongNameIsTruncatedToFit(): void {
    $long = str_repeat('a', UserInterface::USERNAME_MAX_LENGTH);
    $existing = User::create([
      'uid' => 6,
      'name' => $long,
      'mail' => 'long@example.com',
      'status' => 1,
    ]);
    $existing->enforceIsNew();
    $existing->save();
    $this->addSourceUser(9, $long, [2]);

    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users');

    $id_map = $this->getMigration('mukurtu_cms_v3_users')->getIdMap();
    $migrated = User::load(reset($id_map->lookupDestinationIds(['uid' => 9])[0]));
    $this->assertNotNull($migrated, 'The long-named source user was not migrated.');
    $name = $migrated->getAccountName();
    $this->assertLessThanOrEqual(UserInterface::USERNAME_MAX_LENGTH, mb_strlen($name));
    $this->assertStringEndsWith('_1', $name);
    $this->assertSame($long, User::load(6)->getAccountName(), 'This site\'s own long-named account was renamed.');
  }

}
