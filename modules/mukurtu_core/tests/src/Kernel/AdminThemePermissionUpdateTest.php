<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40003(), which grants the admin theme permission.
 *
 * @see mukurtu_update_40003()
 */
#[Group('mukurtu_core')]
class AdminThemePermissionUpdateTest extends KernelTestBase {

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

    $profile_path = \Drupal::service('extension.list.profile')->getPath('mukurtu');
    require_once $profile_path . '/mukurtu.install';
  }

  /**
   * The update hook grants the permission when it's missing.
   */
  public function testUpdateGrantsPermission(): void {
    $role = Role::create(['id' => 'authenticated', 'label' => 'Authenticated user']);
    $role->save();

    mukurtu_update_40003();

    $role = Role::load('authenticated');
    $this->assertTrue($role->hasPermission('view the administration theme'));
  }

  /**
   * The update hook is idempotent when the permission is already granted.
   */
  public function testUpdateIsIdempotent(): void {
    $role = Role::create(['id' => 'authenticated', 'label' => 'Authenticated user']);
    $role->grantPermission('view the administration theme');
    $role->save();

    mukurtu_update_40003();

    $role = Role::load('authenticated');
    $this->assertTrue($role->hasPermission('view the administration theme'));
  }

  /**
   * The update hook is a no-op when the authenticated role doesn't exist.
   */
  public function testUpdateIsNoOpWithoutRole(): void {
    $this->assertNull(Role::load('authenticated'));
    mukurtu_update_40003();
    $this->assertNull(Role::load('authenticated'));
  }

}
