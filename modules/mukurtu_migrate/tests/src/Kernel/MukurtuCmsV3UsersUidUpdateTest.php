<?php

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_migrate_update_40203().
 *
 * Sites that ran mukurtu_migrate_update_40201() (or installed with the
 * config it matched) carry the skip_service_account_uid plugin in the
 * mukurtu_cms_v3_users migration's uid pipeline. That plugin no longer
 * exists; the hook switches the config to its replacement.
 *
 * @see mukurtu_migrate_update_40203()
 * @see \Drupal\Tests\mukurtu_migrate\Kernel\MukurtuCmsV3UsersUidCollisionTest
 */
#[Group('mukurtu_migrate')]
class MukurtuCmsV3UsersUidUpdateTest extends MukurtuCmsV3UsersMigrationTestBase {

  /**
   * The config name of the migration the hook updates.
   */
  const CONFIG = 'migrate_plus.migration.mukurtu_cms_v3_users';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_migrate.install';

    $this->installMigrationConfig('mukurtu_cms_v3_users');
  }

  /**
   * Returns the active 'uid' process pipeline of the migration.
   */
  protected function uidPipeline(): array {
    return \Drupal::configFactory()->get(static::CONFIG)->get('process.uid');
  }

  /**
   * Rewrites the migration's 'uid' pipeline in active config.
   */
  protected function setUidPipeline(array $pipeline): void {
    \Drupal::configFactory()->getEditable(static::CONFIG)->set('process.uid', $pipeline)->save();
  }

  /**
   * Tests that the hook swaps the removed plugin for its replacement.
   */
  public function testSwapsRemovedPlugin(): void {
    $this->setUidPipeline(['plugin' => 'skip_service_account_uid', 'source' => 'uid']);

    $message = mukurtu_migrate_update_40203();

    $this->assertNotNull($message, 'The operator was told nothing about the migration changing.');
    $this->assertSame(['plugin' => 'avoid_uid_collision', 'source' => 'uid'], $this->uidPipeline());
  }

  /**
   * Tests that shipped or already updated config is left alone.
   */
  public function testIsIdempotent(): void {
    $shipped = $this->uidPipeline();
    $this->assertSame('avoid_uid_collision', $shipped['plugin'], 'Precondition: shipped config should already use the new plugin.');

    $this->assertNull(mukurtu_migrate_update_40203());
    $this->assertSame($shipped, $this->uidPipeline());

    $this->setUidPipeline(['plugin' => 'skip_service_account_uid', 'source' => 'uid']);
    $this->assertNotNull(mukurtu_migrate_update_40203());
    $this->assertNull(mukurtu_migrate_update_40203());
    $this->assertSame($shipped, $this->uidPipeline());
  }

  /**
   * Tests that a site-customized pipeline and missing config are not touched.
   */
  public function testLeavesCustomizedOrMissingConfigAlone(): void {
    $custom = ['plugin' => 'get', 'source' => 'uid'];
    $this->setUidPipeline($custom);
    $this->assertNull(mukurtu_migrate_update_40203());
    $this->assertSame($custom, $this->uidPipeline());

    \Drupal::configFactory()->getEditable(static::CONFIG)->delete();
    $this->assertNull(mukurtu_migrate_update_40203());
    $this->assertTrue(\Drupal::configFactory()->get(static::CONFIG)->isNew(), 'The hook created config out of nothing.');
  }

  /**
   * Tests that update 40205 swaps the plain name mapping for the plugin.
   */
  public function testNameMappingIsSwapped(): void {
    $config = \Drupal::configFactory()->getEditable(static::CONFIG);
    $shipped = $config->get('process.name');
    $this->assertSame('avoid_username_collision', $shipped['plugin'], 'Precondition: shipped config should already use the new plugin.');

    // Shipped config is already updated, so the hook is a no-op.
    $this->assertNull(mukurtu_migrate_update_40205());
    $this->assertSame($shipped, \Drupal::configFactory()->get(static::CONFIG)->get('process.name'));

    // An existing site still carries the plain mapping.
    $config->set('process.name', 'name')->save();
    $this->assertNotNull(mukurtu_migrate_update_40205());
    $this->assertSame($shipped, \Drupal::configFactory()->get(static::CONFIG)->get('process.name'));
    $this->assertNull(mukurtu_migrate_update_40205(), 'The hook is not idempotent.');

    // A customized mapping is left alone.
    $custom = ['plugin' => 'callback', 'callable' => 'strtolower', 'source' => 'name'];
    $config->set('process.name', $custom)->save();
    $this->assertNull(mukurtu_migrate_update_40205());
    $this->assertSame($custom, \Drupal::configFactory()->get(static::CONFIG)->get('process.name'));
  }

}
