<?php

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the V3 -> V4 user migration can't overwrite the service account.
 *
 * The mukurtu_submissions module creates a blocked "Submission Forms"
 * account with no explicit uid, so on a fresh site it typically claims a
 * low uid. Before SkipServiceAccountUid and the matching config change,
 * mukurtu_cms_v3_users.yml force-mapped the destination uid to the source
 * site's own uid, so a source user who happened to share that uid got
 * merged onto the service account. This test builds the service account
 * directly (setting the same mukurtu_submissions.settings:service_account_uid
 * config key mukurtu_submissions itself would set) rather than installing
 * the full mukurtu_submissions module, since only that config key matters
 * to the process plugin under test.
 *
 * @see \Drupal\mukurtu_migrate\Plugin\migrate\process\SkipServiceAccountUid
 * @see modules/mukurtu_migrate/config/install/migrate_plus.migration.mukurtu_cms_v3_users.yml
 */
#[Group('mukurtu_migrate')]
class MukurtuCmsV3UsersServiceAccountUidTest extends MukurtuCmsV3UsersMigrationTestBase {

  /**
   * The uid claimed by the fake service account.
   */
  const SERVICE_ACCOUNT_UID = 2;

  /**
   * A source uid that does not collide with the service account.
   */
  const NON_COLLIDING_SOURCE_UID = 5;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createServiceAccount();

    // A source user whose uid collides with the service account's. On the
    // old, buggy pipeline this would have been merged onto the service
    // account.
    $this->addSourceUser(static::SERVICE_ACCOUNT_UID, 'Legacy Person', [2]);
    // A source user with no uid collision, the normal case that must keep
    // working unaffected.
    $this->addSourceUser(static::NON_COLLIDING_SOURCE_UID, 'Other Person', [2]);

    $this->installMigrationConfig('mukurtu_cms_v3_users');
  }

  /**
   * Creates the fake "Submission Forms" service account at a known uid.
   *
   * This mirrors mukurtu_submissions_create_service_account() and the
   * config key mukurtu_submissions_install() sets, without installing the
   * mukurtu_submissions module itself.
   */
  protected function createServiceAccount(): void {
    $account = User::create([
      'uid' => static::SERVICE_ACCOUNT_UID,
      'name' => 'Submission Forms',
      'mail' => 'public-submissions@example.com',
      'status' => 0,
    ]);
    $account->enforceIsNew();
    $account->save();

    \Drupal::configFactory()->getEditable('mukurtu_submissions.settings')
      ->set('service_account_uid', static::SERVICE_ACCOUNT_UID)
      ->save();
  }

  /**
   * Tests that a colliding source uid can't overwrite the service account.
   */
  public function testCollidingUidDoesNotOverwriteServiceAccount(): void {
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users');

    // The service account itself must be untouched.
    $service_account = User::load(static::SERVICE_ACCOUNT_UID);
    $this->assertNotNull($service_account);
    $this->assertSame('Submission Forms', $service_account->getAccountName());
    $this->assertTrue($service_account->isBlocked());

    // The colliding source user must still have been migrated, just onto a
    // different, freshly assigned uid rather than overwriting uid 2.
    $id_map = $this->getMigration('mukurtu_cms_v3_users')->getIdMap();
    $destination_ids = $id_map->lookupDestinationIds(['uid' => static::SERVICE_ACCOUNT_UID]);
    $this->assertNotEmpty($destination_ids, 'The colliding source user was not migrated at all.');
    $migrated_uid = reset($destination_ids[0]);
    $this->assertNotEquals(static::SERVICE_ACCOUNT_UID, $migrated_uid);

    $migrated_user = User::load($migrated_uid);
    $this->assertNotNull($migrated_user);
    $this->assertSame('Legacy Person', $migrated_user->getAccountName());
    $this->assertSame('legacy.person@example.com', $migrated_user->getEmail());
  }

  /**
   * Tests that a non-colliding source uid migrates onto the same uid.
   *
   * Guards against a regression in the normal, non-colliding case.
   */
  public function testNonCollidingUidIsUnaffected(): void {
    $this->executeMigrationWithoutErrors('mukurtu_cms_v3_users');

    $user = User::load(static::NON_COLLIDING_SOURCE_UID);
    $this->assertNotNull($user);
    $this->assertSame('Other Person', $user->getAccountName());
    $this->assertSame('other.person@example.com', $user->getEmail());
  }

}
