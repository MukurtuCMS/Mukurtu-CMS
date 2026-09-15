<?php

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the V3 -> V4 user migration never merges onto existing accounts.
 *
 * The mukurtu_cms_v3_users migration force-maps each user onto its source
 * uid. Two kinds of account can already sit at that uid on the destination
 * site: the blocked "Submission Forms" service account mukurtu_submissions
 * creates at install time (typically uid 2), and any account a site builder
 * made before migrating. Core's entity migrate destination would update such
 * an account in place, merging the legacy user onto it.
 *
 * Simply dropping the uid isn't enough: Drupal then assigns the next free
 * uid, which a later source user is force-mapped onto in turn. On a real
 * migration this left source uids 2 and 3 both mapped to destination uid 3.
 *
 * @see \Drupal\mukurtu_migrate\Plugin\migrate\process\AvoidUidCollision
 * @see modules/mukurtu_migrate/config/install/migrate_plus.migration.mukurtu_cms_v3_users.yml
 */
#[Group('mukurtu_migrate')]
class MukurtuCmsV3UsersUidCollisionTest extends MukurtuCmsV3UsersMigrationTestBase {

  /**
   * The uid claimed by the fake service account.
   */
  const SERVICE_ACCOUNT_UID = 2;

  /**
   * The uid of an account a site builder created before migrating.
   */
  const SITE_BUILDER_UID = 4;

  /**
   * The highest source uid; allocated uids must land above it.
   */
  const HIGHEST_SOURCE_UID = 5;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createExistingAccount(static::SERVICE_ACCOUNT_UID, 'Submission Forms', 0);
    $this->createExistingAccount(static::SITE_BUILDER_UID, 'Site Builder', 1);

    // Source uids 2 and 4 collide with the accounts above; 3 and 5 don't.
    // Source uid 3 is the one that used to be merged onto the account
    // Drupal auto-assigned to source uid 2.
    $this->addSourceUser(2, 'Legacy Person', [2]);
    $this->addSourceUser(3, 'Next Person', [2]);
    $this->addSourceUser(4, 'Other Legacy Person', [2]);
    $this->addSourceUser(static::HIGHEST_SOURCE_UID, 'Last Person', [2]);

    $this->installMigrationConfig('mukurtu_cms_v3_users');
  }

  /**
   * Creates an account at a known uid before the migration runs.
   */
  protected function createExistingAccount(int $uid, string $name, int $status): void {
    $account = User::create([
      'uid' => $uid,
      'name' => $name,
      'mail' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
      'status' => $status,
    ]);
    $account->enforceIsNew();
    $account->save();
  }

  /**
   * Returns the destination uid the migration mapped a source uid to.
   */
  protected function destinationUid(int $source_uid): int {
    $id_map = $this->getMigration('mukurtu_cms_v3_users')->getIdMap();
    $destination_ids = $id_map->lookupDestinationIds(['uid' => $source_uid]);
    $this->assertNotEmpty($destination_ids, "Source user $source_uid was not migrated at all.");
    return (int) reset($destination_ids[0]);
  }

  /**
   * Tests that colliding source uids get fresh uids and merge onto nothing.
   */
  public function testCollidingUidsAreReallocated(): void {
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users');

    // The pre-existing accounts are untouched.
    $service_account = User::load(static::SERVICE_ACCOUNT_UID);
    $this->assertSame('Submission Forms', $service_account->getAccountName());
    $this->assertTrue($service_account->isBlocked());
    $this->assertSame('Site Builder', User::load(static::SITE_BUILDER_UID)->getAccountName());

    // Non-colliding source users keep their uids.
    $this->assertSame(3, $this->destinationUid(3));
    $this->assertSame('Next Person', User::load(3)->getAccountName());
    $this->assertSame(5, $this->destinationUid(5));
    $this->assertSame('Last Person', User::load(5)->getAccountName());

    // Colliding source users land above every source uid, on distinct
    // accounts of their own.
    $legacy_uid = $this->destinationUid(2);
    $other_legacy_uid = $this->destinationUid(4);
    $this->assertGreaterThan(static::HIGHEST_SOURCE_UID, $legacy_uid);
    $this->assertGreaterThan(static::HIGHEST_SOURCE_UID, $other_legacy_uid);
    $this->assertNotSame($legacy_uid, $other_legacy_uid);
    $this->assertSame('Legacy Person', User::load($legacy_uid)->getAccountName());
    $this->assertSame('legacy.person@example.com', User::load($legacy_uid)->getEmail());
    $this->assertSame('Other Legacy Person', User::load($other_legacy_uid)->getAccountName());

    // Four source users became four distinct destination accounts.
    $this->assertCount(4, array_unique([3, 5, $legacy_uid, $other_legacy_uid]));
  }

  /**
   * Tests that re-running with --update updates the reallocated accounts.
   */
  public function testUpdateRunKeepsReallocatedUids(): void {
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users');
    $legacy_uid = $this->destinationUid(2);

    // Change the source, then re-import every row.
    $this->sourceDatabase->update('users')
      ->fields(['name' => 'Renamed Legacy Person'])
      ->condition('uid', 2)
      ->execute();
    $migration = $this->getMigration('mukurtu_cms_v3_users');
    $migration->getIdMap()->prepareUpdate();
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users');

    $this->assertSame($legacy_uid, $this->destinationUid(2), 'The update run moved the reallocated user to another uid.');
    $this->assertSame('Renamed Legacy Person', User::load($legacy_uid)->getAccountName());
    $this->assertSame('Submission Forms', User::load(static::SERVICE_ACCOUNT_UID)->getAccountName());
    $this->assertCount(4, array_unique([
      $this->destinationUid(2),
      $this->destinationUid(3),
      $this->destinationUid(4),
      $this->destinationUid(5),
    ]));
  }

}
