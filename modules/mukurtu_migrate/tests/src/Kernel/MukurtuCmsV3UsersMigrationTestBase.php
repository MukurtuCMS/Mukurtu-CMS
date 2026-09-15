<?php

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;

/**
 * Shared fixtures for the V3 -> V4 user migration tests.
 *
 * Builds the minimal fake D7 source tables the d7_user source plugin needs
 * and installs the real, shipped migration config from mukurtu_migrate's
 * config/install directory, so subclasses exercise exactly what will ship.
 */
abstract class MukurtuCmsV3UsersMigrationTestBase extends MigrateTestBase {

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
    // Only needed for its config schema (mukurtu_submissions.settings), which
    // the skip_service_account_uid process plugin reads. hook_install() never
    // runs from a Kernel test's $modules list, so its hard dependencies
    // (mukurtu_core, mukurtu_media, etc.) are never pulled in and don't need
    // to be listed here either. See MukurtuSubmissionsKernelTestBase's
    // docblock for the same reasoning.
    'mukurtu_submissions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->createFakeD7SourceDatabase();
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

    // D7 {users_roles} table. User::prepareRow() unconditionally queries it
    // and hands the rids to the 'roles' process pipeline as an array. D7
    // never stores the anonymous/authenticated rids (1 and 2) here, so a
    // plain member has no rows at all.
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
    // empty means every fixture user has no extra field values to resolve.
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
  }

  /**
   * Adds a user to the fake D7 source database.
   *
   * @param int $uid
   *   The source uid.
   * @param string $name
   *   The source account name. The mail and init columns are derived from it.
   * @param int[] $rids
   *   The D7 role ids to store in {users_roles}. Pass an empty array for a
   *   plain member with no extra roles.
   */
  protected function addSourceUser(int $uid, string $name, array $rids = []): void {
    $mail = strtolower(str_replace(' ', '.', $name)) . '@example.com';
    $this->sourceDatabase->insert('users')->fields([
      'uid' => $uid,
      'name' => $name,
      'pass' => 'hashed-password',
      'mail' => $mail,
      'signature' => '',
      'signature_format' => '',
      'created' => 1000000000,
      'access' => 1000000001,
      'login' => 1000000001,
      'status' => 1,
      'timezone' => 'America/New_York',
      'language' => '',
      'picture' => 0,
      'init' => $mail,
      'data' => serialize([]),
    ])->execute();
    foreach ($rids as $rid) {
      $this->sourceDatabase->insert('users_roles')->fields([
        'uid' => $uid,
        'rid' => $rid,
      ])->execute();
    }
  }

  /**
   * Installs a real, shipped mukurtu_migrate migration config.
   *
   * This loads the actual YAML file from mukurtu_migrate's config/install
   * directory (rather than hand-copying its process pipeline into the
   * test), so the test exercises exactly what will ship.
   *
   * @param string $id
   *   The migration id, e.g. 'mukurtu_cms_v3_users'.
   */
  protected function installMigrationConfig(string $id): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    $file = $module_path . '/config/install/migrate_plus.migration.' . $id . '.yml';
    $this->assertFileExists($file);
    $data = Yaml::decode(file_get_contents($file));
    \Drupal::entityTypeManager()->getStorage('migration')->create($data)->save();
  }

  /**
   * Runs a migration and asserts it produced no error messages.
   *
   * @param string $id
   *   The migration id.
   */
  protected function executeMigrationWithoutErrors(string $id): void {
    $this->startCollectingMessages();
    $this->executeMigration($id);
    $this->assertEmpty($this->migrateMessages['error'] ?? [], print_r($this->migrateMessages['error'] ?? [], TRUE));
    $this->assertSame(0, $this->getMigration($id)->getIdMap()->errorCount(), "Migration $id recorded failed rows in its id map.");
  }

}
