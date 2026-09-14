<?php

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;
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
class MukurtuCmsV3UsersServiceAccountUidTest extends MigrateTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'migrate',
    'migrate_drupal',
    'migrate_plus',
    'search_api',
    'mukurtu_migrate',
    // Only needed for its config schema (mukurtu_submissions.settings) -
    // hook_install() never runs from a Kernel test's $modules list, so its
    // hard dependencies (mukurtu_core, mukurtu_media, etc.) are never
    // pulled in and don't need to be listed here either. See
    // MukurtuSubmissionsKernelTestBase's docblock for the same reasoning.
    'mukurtu_submissions',
  ];

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

    $this->installEntitySchema('user');
    $this->createServiceAccount();
    $this->createFakeD7SourceDatabase();
    $this->installUsersMigrationConfig();
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
   * Builds the minimal fake D7 source tables the d7_user source needs.
   */
  protected function createFakeD7SourceDatabase(): void {
    $schema = $this->sourceDatabase->schema();

    // D7 {system} table. DrupalSqlBase::checkRequirements() looks up the
    // 'user' module here (the d7_user source plugin's source_module) and
    // throws a RequirementsException if it isn't found and enabled.
    $schema->createTable('system', [
      'fields' => [
        'filename' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE, 'default' => ''],
        'name' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE, 'default' => ''],
        'type' => ['type' => 'varchar', 'length' => 12, 'not null' => TRUE, 'default' => ''],
        'owner' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE, 'default' => ''],
        'status' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'bootstrap' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'schema_version' => ['type' => 'int', 'not null' => TRUE, 'default' => -1],
        'weight' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'info' => ['type' => 'text', 'not null' => FALSE],
      ],
      'primary key' => ['filename'],
    ]);
    $this->sourceDatabase->insert('system')->fields([
      'filename' => 'modules/user/user.module',
      'name' => 'user',
      'type' => 'module',
      'owner' => '',
      'status' => 1,
      'bootstrap' => 0,
      'schema_version' => 7000,
      'weight' => 0,
      'info' => '',
    ])->execute();

    // D7 {users} table, the source table for the d7_user source plugin.
    $schema->createTable('users', [
      'fields' => [
        'uid' => ['type' => 'int', 'not null' => TRUE],
        'name' => ['type' => 'varchar', 'length' => 60, 'not null' => TRUE, 'default' => ''],
        'pass' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE, 'default' => ''],
        'mail' => ['type' => 'varchar', 'length' => 254, 'not null' => FALSE],
        'signature' => ['type' => 'text', 'not null' => FALSE],
        'signature_format' => ['type' => 'varchar', 'length' => 255, 'not null' => FALSE],
        'created' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'access' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'login' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'status' => ['type' => 'int', 'size' => 'tiny', 'not null' => TRUE, 'default' => 0],
        'timezone' => ['type' => 'varchar', 'length' => 32, 'not null' => FALSE],
        'language' => ['type' => 'varchar', 'length' => 12, 'not null' => TRUE, 'default' => ''],
        'picture' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'init' => ['type' => 'varchar', 'length' => 254, 'not null' => FALSE],
        'data' => ['type' => 'text', 'not null' => FALSE],
      ],
      'primary key' => ['uid'],
    ]);

    // D7 {users_roles} table. User::prepareRow() unconditionally queries it,
    // and StaticMap (the 'roles' process pipeline in
    // mukurtu_cms_v3_users.yml) throws when given an empty array, so every
    // fixture user below needs at least one row here.
    $schema->createTable('users_roles', [
      'fields' => [
        'uid' => ['type' => 'int', 'not null' => TRUE],
        'rid' => ['type' => 'int', 'not null' => TRUE],
      ],
      'primary key' => ['uid', 'rid'],
    ]);

    // D7 Field API tables. FieldableEntity::getFields() unconditionally
    // queries {field_config_instance} (joined to {field_config}) to discover
    // which CCK fields are attached to the 'user' bundle. Leaving these
    // empty means every fixture user below has no extra field values to
    // resolve.
    $schema->createTable('field_config', [
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'field_name' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE, 'default' => ''],
        'translatable' => ['type' => 'int', 'size' => 'tiny', 'not null' => TRUE, 'default' => 0],
      ],
      'primary key' => ['id'],
    ]);
    $schema->createTable('field_config_instance', [
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'field_id' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'field_name' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE, 'default' => ''],
        'entity_type' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE, 'default' => ''],
        'bundle' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE, 'default' => ''],
        'deleted' => ['type' => 'int', 'size' => 'tiny', 'not null' => TRUE, 'default' => 0],
      ],
      'primary key' => ['id'],
    ]);

    // Row 1: a source user whose uid collides with the service account's.
    // On the old, buggy pipeline this would have been merged onto the
    // service account.
    $this->sourceDatabase->insert('users')->fields([
      'uid' => static::SERVICE_ACCOUNT_UID,
      'name' => 'Legacy Person',
      'pass' => 'hashed-password',
      'mail' => 'legacy@example.com',
      'signature' => '',
      'signature_format' => '',
      'created' => 1000000000,
      'access' => 1000000001,
      'login' => 1000000001,
      'status' => 1,
      'timezone' => 'America/New_York',
      'language' => '',
      'picture' => 0,
      'init' => 'legacy@example.com',
      'data' => serialize([]),
    ])->execute();
    $this->sourceDatabase->insert('users_roles')->fields([
      'uid' => static::SERVICE_ACCOUNT_UID,
      'rid' => 2,
    ])->execute();

    // Row 2: a source user with no uid collision, the normal case that must
    // keep working unaffected.
    $this->sourceDatabase->insert('users')->fields([
      'uid' => static::NON_COLLIDING_SOURCE_UID,
      'name' => 'Other Person',
      'pass' => 'hashed-password',
      'mail' => 'other@example.com',
      'signature' => '',
      'signature_format' => '',
      'created' => 1000000000,
      'access' => 1000000001,
      'login' => 1000000001,
      'status' => 1,
      'timezone' => 'America/New_York',
      'language' => '',
      'picture' => 0,
      'init' => 'other@example.com',
      'data' => serialize([]),
    ])->execute();
    $this->sourceDatabase->insert('users_roles')->fields([
      'uid' => static::NON_COLLIDING_SOURCE_UID,
      'rid' => 2,
    ])->execute();
  }

  /**
   * Installs the real, shipped mukurtu_cms_v3_users migration config.
   *
   * This loads the actual YAML file from mukurtu_migrate's config/install
   * directory (rather than hand-copying its process pipeline into the
   * test), so the test exercises exactly what will ship.
   */
  protected function installUsersMigrationConfig(): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    $file = $module_path . '/config/install/migrate_plus.migration.mukurtu_cms_v3_users.yml';
    $data = Yaml::decode(file_get_contents($file));
    \Drupal::entityTypeManager()->getStorage('migration')->create($data)->save();
  }

  /**
   * Tests that a colliding source uid can't overwrite the service account.
   */
  public function testCollidingUidDoesNotOverwriteServiceAccount(): void {
    $this->startCollectingMessages();
    $this->executeMigration('mukurtu_cms_v3_users');
    $this->assertEmpty($this->migrateMessages['error'] ?? [], print_r($this->migrateMessages['error'] ?? [], TRUE));

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
    $this->assertSame('legacy@example.com', $migrated_user->getEmail());
  }

  /**
   * Tests that a non-colliding source uid migrates onto the same uid.
   *
   * Guards against a regression in the normal, non-colliding case.
   */
  public function testNonCollidingUidIsUnaffected(): void {
    $this->startCollectingMessages();
    $this->executeMigration('mukurtu_cms_v3_users');
    $this->assertEmpty($this->migrateMessages['error'] ?? [], print_r($this->migrateMessages['error'] ?? [], TRUE));

    $user = User::load(static::NON_COLLIDING_SOURCE_UID);
    $this->assertNotNull($user);
    $this->assertSame('Other Person', $user->getAccountName());
    $this->assertSame('other@example.com', $user->getEmail());
  }

}
