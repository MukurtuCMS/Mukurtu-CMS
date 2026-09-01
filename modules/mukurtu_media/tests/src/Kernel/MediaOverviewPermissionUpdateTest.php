<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_media_update_40021(), which grants a media permission.
 *
 * @see mukurtu_media_update_40021()
 */
#[Group('mukurtu_media')]
class MediaOverviewPermissionUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_media');
    require_once $module_path . '/mukurtu_media.install';
  }

  /**
   * The update hook grants 'access media overview' to mukurtu_manager.
   */
  public function testUpdateGrantsMediaOverviewPermission(): void {
    $role = Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager']);
    $role->save();
    $this->assertFalse($role->hasPermission('access media overview'));

    mukurtu_media_update_40021();

    $role = Role::load('mukurtu_manager');
    $this->assertTrue($role->hasPermission('access media overview'));
  }

  /**
   * The update hook is a no-op when the mukurtu_manager role doesn't exist.
   */
  public function testUpdateIsNoOpWithoutRole(): void {
    $this->assertNull(Role::load('mukurtu_manager'));
    mukurtu_media_update_40021();
    $this->assertNull(Role::load('mukurtu_manager'));
  }

  /**
   * The update hook leaves unrelated permissions on the role untouched.
   */
  public function testUpdatePreservesOtherPermissions(): void {
    $role = Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager']);
    $role->grantPermission('access content');
    $role->save();

    mukurtu_media_update_40021();

    $role = Role::load('mukurtu_manager');
    $this->assertTrue($role->hasPermission('access content'));
    $this->assertTrue($role->hasPermission('access media overview'));
  }

}
