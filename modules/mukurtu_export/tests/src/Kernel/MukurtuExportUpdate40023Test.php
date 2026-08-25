<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_export_update_40023(), which grants Mukurtu Manager the
 * flag/unflag export-list permissions already held by Mukurtu Roundtrip
 * Manager.
 *
 * @see mukurtu_export_update_40023()
 */
#[Group('mukurtu_export')]
class MukurtuExportUpdate40023Test extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'field',
    'file',
    'flag',
    'geofield',
    'image',
    'leaflet',
    'media',
    'mukurtu_core',
    'mukurtu_export',
    'mukurtu_multipage_items',
    'node',
    'system',
    'user',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('flagging');

    // Role::calculateDependencies() silently strips any permission string
    // that isn't a real, currently-registered permission -- for the flag
    // permissions, that registration is dynamic (flag_permission() derives
    // them from actually-installed Flag config entities), so both flags
    // must be installed here, not just the 'flag' module enabled.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_export');
    $storage = new FileStorage($module_path . '/config/install');
    foreach (['flag.flag.export_content', 'flag.flag.export_media'] as $name) {
      \Drupal::configFactory()->getEditable($name)->setData($storage->read($name))->save();
    }

    require_once $module_path . '/mukurtu_export.install';

    $role = Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager']);
    $role->grantPermission('access mukurtu export');
    $role->save();
  }

  /**
   * The update hook grants all 4 flag/unflag permissions.
   */
  public function testUpdateGrantsFlagPermissions(): void {
    mukurtu_export_update_40023();

    $role = Role::load('mukurtu_manager');
    foreach (['flag export_content', 'unflag export_content', 'flag export_media', 'unflag export_media'] as $permission) {
      $this->assertTrue($role->hasPermission($permission), "Expected mukurtu_manager to hold '$permission' after update.");
    }
  }

  /**
   * The update hook is a no-op (doesn't fatal) when the role is missing.
   */
  public function testUpdateIsNoOpWhenRoleMissing(): void {
    Role::load('mukurtu_manager')->delete();
    mukurtu_export_update_40023();
    $this->assertNull(Role::load('mukurtu_manager'));
  }

}
