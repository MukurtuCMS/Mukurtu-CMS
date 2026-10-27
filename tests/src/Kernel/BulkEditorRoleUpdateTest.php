<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40035().
 *
 * Existing sites get the Bulk Editor role, and Mukurtu Managers get the
 * "Use bulk editing" permission.
 */
#[Group('mukurtu')]
class BulkEditorRoleUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'geofield',
    'image',
    'leaflet',
    'media',
    'mukurtu_core',
    'mukurtu_protocol',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'views_bulk_operations',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once $this->root . '/' . \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/mukurtu.install';
    Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager'])->grantPermission('access content')->save();
  }

  /**
   * The role is created from the shipped config and managers gain access.
   */
  public function testAddsRoleAndManagerPermission(): void {
    $message = mukurtu_update_40035();

    $role = Role::load('mukurtu_bulk_editor');
    $this->assertNotNull($role);
    $this->assertSame('Bulk Editor', $role->label());
    $this->assertSame(['use mukurtu bulk edit'], $role->getPermissions());
    $manager = Role::load('mukurtu_manager');
    $this->assertTrue($manager->hasPermission('use mukurtu bulk edit'));
    $this->assertTrue($manager->hasPermission('access content'));
    $this->assertStringContainsString('Bulk Editor', $message);
  }

  /**
   * A site that already has the role keeps its own version of it.
   */
  public function testKeepsExistingRole(): void {
    Role::create(['id' => 'mukurtu_bulk_editor', 'label' => 'Site label'])->grantPermission('access content')->save();

    mukurtu_update_40035();

    $role = Role::load('mukurtu_bulk_editor');
    $this->assertSame('Site label', $role->label());
    $this->assertSame(['access content'], $role->getPermissions());
  }

  /**
   * Running twice is a no-op the second time.
   */
  public function testIsIdempotent(): void {
    mukurtu_update_40035();
    $message = mukurtu_update_40035();

    $this->assertStringContainsString('nothing to change', $message);
  }

}
