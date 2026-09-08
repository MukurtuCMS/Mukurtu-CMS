<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40004(), which grants the message subscribe permission.
 *
 * The 'administer message subscribe' permission is defined by
 * message_subscribe_ui's hook_permission(); Role::calculateDependencies()
 * silently strips any permission string that no enabled module grants, so
 * the full dependency chain has to be installed for hasPermission()/
 * grantPermission() to actually stick.
 *
 * @see mukurtu_update_40004()
 */
#[Group('mukurtu_core')]
class MessageSubscribePermissionUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'text',
    'message',
    'message_notify',
    'flag',
    'message_subscribe',
    'message_subscribe_ui',
    'views',
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
    $role = Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager']);
    $role->save();

    mukurtu_update_40004();

    $role = Role::load('mukurtu_manager');
    $this->assertTrue($role->hasPermission('administer message subscribe'));
  }

  /**
   * The update hook is idempotent when the permission is already granted.
   */
  public function testUpdateIsIdempotent(): void {
    $role = Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager']);
    $role->grantPermission('administer message subscribe');
    $role->save();

    mukurtu_update_40004();

    $role = Role::load('mukurtu_manager');
    $this->assertTrue($role->hasPermission('administer message subscribe'));
  }

  /**
   * The update hook is a no-op when the mukurtu_manager role doesn't exist.
   */
  public function testUpdateIsNoOpWithoutRole(): void {
    $this->assertNull(Role::load('mukurtu_manager'));
    mukurtu_update_40004();
    $this->assertNull(Role::load('mukurtu_manager'));
  }

}
