<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40224().
 *
 * Content revision history moves from every logged-in user to Mukurtu
 * Managers (administrators see everything).
 */
#[Group('mukurtu_core')]
class RevisionHistoryPermissionUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once $this->root . '/' . \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/modules/mukurtu_core/mukurtu_core.install';
  }

  /**
   * Authenticated loses the permission, Managers gain it, once.
   */
  public function testMovesPermission(): void {
    Role::create(['id' => 'authenticated', 'label' => 'Authenticated user'])
      ->grantPermission('view all revisions')
      ->grantPermission('access content')
      ->save();
    Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager'])->save();

    $this->assertSame('Only administrators and Mukurtu Managers can now see content revision history.', mukurtu_core_update_40224());

    $this->assertFalse(Role::load('authenticated')->hasPermission('view all revisions'));
    $this->assertTrue(Role::load('authenticated')->hasPermission('access content'));
    $this->assertTrue(Role::load('mukurtu_manager')->hasPermission('view all revisions'));

    $this->assertSame('Content revision history is already limited to administrators and Mukurtu Managers; nothing to change.', mukurtu_core_update_40224());
  }

  /**
   * A site without a Mukurtu Manager role is still cleaned up.
   */
  public function testWithoutManagerRole(): void {
    Role::create(['id' => 'authenticated', 'label' => 'Authenticated user'])
      ->grantPermission('view all revisions')
      ->save();

    $this->assertSame('Only administrators and Mukurtu Managers can now see content revision history.', mukurtu_core_update_40224());
    $this->assertFalse(Role::load('authenticated')->hasPermission('view all revisions'));
  }

}
