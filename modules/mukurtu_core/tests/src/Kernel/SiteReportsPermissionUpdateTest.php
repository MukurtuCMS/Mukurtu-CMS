<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;

/**
 * Tests mukurtu_core_update_40101(), which grants the Mukurtu Manager role
 * permission to view site reports.
 *
 * @see mukurtu_core_update_40101()
 * @group mukurtu_core
 */
class SiteReportsPermissionUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once $module_path . '/mukurtu_core.install';
  }

  /**
   * The update hook grants the permission when it's missing.
   */
  public function testUpdateGrantsSiteReportsPermission(): void {
    $role = Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager']);
    $role->save();

    mukurtu_core_update_40101();

    $role = Role::load('mukurtu_manager');
    $this->assertTrue($role->hasPermission('access site reports'));
  }

  /**
   * The update hook leaves other permissions on the role untouched.
   */
  public function testUpdatePreservesOtherPermissions(): void {
    $role = Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager']);
    $role->grantPermission('access content');
    $role->save();

    mukurtu_core_update_40101();

    $role = Role::load('mukurtu_manager');
    $this->assertTrue($role->hasPermission('access content'));
  }

  /**
   * The update hook is idempotent when the permission is already granted.
   */
  public function testUpdateIsIdempotentWhenAlreadyGranted(): void {
    $role = Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager']);
    $role->grantPermission('access site reports');
    $role->save();

    mukurtu_core_update_40101();

    $role = Role::load('mukurtu_manager');
    $this->assertTrue($role->hasPermission('access site reports'));
  }

  /**
   * The update hook is a no-op when the mukurtu_manager role doesn't exist.
   */
  public function testUpdateIsNoOpWithoutRole(): void {
    $this->assertNull(Role::load('mukurtu_manager'));
    mukurtu_core_update_40101();
    $this->assertNull(Role::load('mukurtu_manager'));
  }

}
