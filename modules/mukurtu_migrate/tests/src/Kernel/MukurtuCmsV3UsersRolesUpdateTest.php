<?php

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_migrate_update_40202().
 *
 * Migration config is copied into active config when mukurtu_migrate is
 * installed, so a site that installed before the array_unique step was added
 * to the user migrations' 'roles' pipeline keeps the old, crashing pipeline
 * until the update hook appends it.
 *
 * The hook also covers mukurtu_cms_v3_users_uid1, which mapped roles when
 * the hook was written. Shipped config no longer does (see
 * mukurtu_migrate_update_40204()), so only the migration that still maps
 * roles is exercised here; the hook skips a migration without the mapping.
 *
 * @see mukurtu_migrate_update_40202()
 * @see \Drupal\Tests\mukurtu_migrate\Kernel\MukurtuCmsV3UsersRolesTest
 */
#[Group('mukurtu_migrate')]
class MukurtuCmsV3UsersRolesUpdateTest extends MukurtuCmsV3UsersMigrationTestBase {

  /**
   * The migrations the hook updates.
   */
  const MIGRATIONS = ['mukurtu_cms_v3_users'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_migrate.install';

    foreach (static::MIGRATIONS as $id) {
      $this->installMigrationConfig($id);
    }
  }

  /**
   * Returns the active 'roles' process pipeline of a migration.
   */
  protected function rolesPipeline(string $id): array {
    return \Drupal::configFactory()->get('migrate_plus.migration.' . $id)->get('process.roles');
  }

  /**
   * Rewrites a migration's 'roles' pipeline in active config.
   */
  protected function setRolesPipeline(string $id, array $pipeline): void {
    \Drupal::configFactory()->getEditable('migrate_plus.migration.' . $id)
      ->set('process.roles', $pipeline)
      ->save();
  }

  /**
   * Strips the array_unique step so the config looks like a pre-fix site.
   */
  protected function simulateOldPipeline(string $id): void {
    $pipeline = array_values(array_filter(
      $this->rolesPipeline($id),
      static fn (array $step): bool => ($step['plugin'] ?? NULL) !== 'array_unique'
    ));
    $this->setRolesPipeline($id, $pipeline);
    $this->assertCount(1, $this->rolesPipeline($id), 'Precondition: the shipped pipeline should be static_map plus array_unique only.');
    $this->assertSame('static_map', $this->rolesPipeline($id)[0]['plugin']);
  }

  /**
   * Tests that the hook appends array_unique to the shipped pipeline.
   */
  public function testAppendsArrayUniqueToAnExistingSite(): void {
    $before = [];
    foreach (static::MIGRATIONS as $id) {
      $this->simulateOldPipeline($id);
      $before[$id] = $this->rolesPipeline($id);
    }

    $message = mukurtu_migrate_update_40202();

    $this->assertNotNull($message, 'The operator was told nothing about the migrations changing.');
    foreach (static::MIGRATIONS as $id) {
      $after = $this->rolesPipeline($id);
      $this->assertStringContainsString($id, $message);
      $this->assertCount(2, $after);
      $this->assertSame(['plugin' => 'array_unique'], $after[1], "$id did not get the array_unique step.");
      // The hook appends; it does not rewrite the static_map step.
      $this->assertSame($before[$id][0], $after[0], "The hook altered the static_map step of $id.");
    }
  }

  /**
   * Tests that a fresh install (or an already updated site) is left alone.
   */
  public function testIsIdempotent(): void {
    foreach (static::MIGRATIONS as $id) {
      $this->assertSame('array_unique', $this->rolesPipeline($id)[1]['plugin'] ?? NULL, 'Precondition: shipped config should already carry the array_unique step.');
    }
    $shipped = array_map([$this, 'rolesPipeline'], array_combine(static::MIGRATIONS, static::MIGRATIONS));

    $this->assertNull(mukurtu_migrate_update_40202(), 'The hook reported a change on config that already had the step.');
    foreach (static::MIGRATIONS as $id) {
      $this->assertSame($shipped[$id], $this->rolesPipeline($id), "The hook changed $id even though it already had the step.");
    }

    // Running it on an old site twice adds the step exactly once.
    foreach (static::MIGRATIONS as $id) {
      $this->simulateOldPipeline($id);
    }
    $this->assertNotNull(mukurtu_migrate_update_40202());
    $this->assertNull(mukurtu_migrate_update_40202());
    foreach (static::MIGRATIONS as $id) {
      $this->assertSame($shipped[$id], $this->rolesPipeline($id));
    }
  }

  /**
   * Tests that a site-customized pipeline is not touched.
   */
  public function testLeavesCustomizedPipelineAlone(): void {
    $custom = [
      ['plugin' => 'migration_lookup', 'migration' => 'd7_user_role', 'source' => 'roles'],
    ];
    foreach (static::MIGRATIONS as $id) {
      $this->setRolesPipeline($id, $custom);
    }

    $this->assertNull(mukurtu_migrate_update_40202());
    foreach (static::MIGRATIONS as $id) {
      $this->assertSame($custom, $this->rolesPipeline($id), "The hook rewrote the customized pipeline of $id.");
    }
  }

  /**
   * Tests that a site without the migration config installed is skipped.
   */
  public function testSkipsMissingConfig(): void {
    foreach (static::MIGRATIONS as $id) {
      \Drupal::configFactory()->getEditable('migrate_plus.migration.' . $id)->delete();
    }

    $this->assertNull(mukurtu_migrate_update_40202());
    foreach (static::MIGRATIONS as $id) {
      $this->assertTrue(\Drupal::configFactory()->get('migrate_plus.migration.' . $id)->isNew(), "The hook created config for $id out of nothing.");
    }
  }

}
