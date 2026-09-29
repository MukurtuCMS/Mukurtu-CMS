<?php

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_migrate_update_40204().
 *
 * Migration config is copied into active config when mukurtu_migrate is
 * installed, so a site that installed before the roles and status mappings
 * were dropped from the admin migration keeps overwriting its own user 1
 * until the update hook removes them.
 *
 * @see mukurtu_migrate_update_40204()
 * @see \Drupal\Tests\mukurtu_migrate\Kernel\MukurtuCmsV3UsersAccountFieldsTest
 */
#[Group('mukurtu_migrate')]
class MukurtuCmsV3UsersUid1ProcessUpdateTest extends MukurtuCmsV3UsersMigrationTestBase {

  /**
   * The config name of the migration the hook updates.
   */
  const CONFIG = 'migrate_plus.migration.mukurtu_cms_v3_users_uid1';

  /**
   * The roles pipeline the migration used to ship.
   */
  const OLD_ROLES = [
    [
      'plugin' => 'static_map',
      'source' => 'roles',
      'map' => [2 => 'authenticated', 4 => 'mukurtu_manager', 6 => 'mukurtu_manager'],
      'default_value' => 'authenticated',
    ],
    ['plugin' => 'array_unique'],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_migrate.install';

    $this->installMigrationConfig('mukurtu_cms_v3_users_uid1');
  }

  /**
   * Returns the migration's active process pipeline.
   */
  protected function process(): array {
    return \Drupal::configFactory()->get(static::CONFIG)->get('process');
  }

  /**
   * Puts the pre-fix mappings back, as an existing site would have them.
   */
  protected function restoreOldProcess(): void {
    $process = $this->process();
    $process['status'] = 'status';
    $process['roles'] = static::OLD_ROLES;
    \Drupal::configFactory()->getEditable(static::CONFIG)->set('process', $process)->save();
  }

  /**
   * Tests that the hook drops both mappings from an existing site.
   */
  public function testRemovesRolesAndStatus(): void {
    $this->restoreOldProcess();
    $before = $this->process();

    $message = mukurtu_migrate_update_40204();

    $this->assertNotNull($message, 'The operator was told nothing about the migration changing.');
    $this->assertStringContainsString('status', $message);
    $this->assertStringContainsString('roles', $message);

    $after = $this->process();
    $this->assertArrayNotHasKey('roles', $after);
    $this->assertArrayNotHasKey('status', $after);

    // Everything else is left exactly as it was.
    unset($before['roles'], $before['status']);
    $this->assertSame($before, $after, 'The hook changed mappings other than the two it removes.');
  }

  /**
   * Tests that shipped or already updated config is left alone.
   */
  public function testIsIdempotent(): void {
    $shipped = $this->process();
    $this->assertArrayNotHasKey('roles', $shipped, 'Precondition: shipped config should no longer map roles.');
    $this->assertArrayNotHasKey('status', $shipped, 'Precondition: shipped config should no longer map status.');

    $this->assertNull(mukurtu_migrate_update_40204());
    $this->assertSame($shipped, $this->process());

    $this->restoreOldProcess();
    $this->assertNotNull(mukurtu_migrate_update_40204());
    $this->assertNull(mukurtu_migrate_update_40204());
    $this->assertSame($shipped, $this->process());
  }

  /**
   * Tests that customized mappings and missing config are not touched.
   */
  public function testLeavesCustomizedOrMissingConfigAlone(): void {
    $process = $this->process();
    $process['status'] = ['plugin' => 'default_value', 'default_value' => 1];
    $process['roles'] = [['plugin' => 'migration_lookup', 'migration' => 'd7_user_role']];
    \Drupal::configFactory()->getEditable(static::CONFIG)->set('process', $process)->save();

    $this->assertNull(mukurtu_migrate_update_40204());
    $this->assertSame($process, $this->process(), 'The hook rewrote customized mappings.');

    \Drupal::configFactory()->getEditable(static::CONFIG)->delete();
    $this->assertNull(mukurtu_migrate_update_40204());
    $this->assertTrue(\Drupal::configFactory()->get(static::CONFIG)->isNew(), 'The hook created config out of nothing.');
  }

}
