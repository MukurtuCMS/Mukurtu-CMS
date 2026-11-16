<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40226().
 *
 * Mukurtu Managers get the permissions behind admin-only dashboard settings
 * links, skipping any whose providing module is not installed.
 */
#[Group('mukurtu_core')]
class ManagerDashboardSettingsPermissionUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Honeypot stands in for the providing modules; the rest are deliberately
   * absent so the hook must skip their permissions.
   */
  protected static $modules = ['honeypot', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once $this->root . '/' . \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/modules/mukurtu_core/mukurtu_core.install';
  }

  /**
   * Defined permissions are granted; undefined ones are skipped.
   */
  public function testGrantsAvailablePermissions(): void {
    Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager'])
      ->grantPermission('administer site configuration')
      ->save();

    mukurtu_core_update_40226();

    $manager = Role::load('mukurtu_manager');
    $this->assertTrue($manager->hasPermission('administer honeypot'));
    $this->assertTrue($manager->hasPermission('administer site configuration'));
    $this->assertFalse($manager->hasPermission('administer google_tag_container'));
    $this->assertFalse($manager->hasPermission('administer klaro'));

    // A second run changes nothing.
    $before = $manager->getPermissions();
    mukurtu_core_update_40226();
    $this->assertSame($before, Role::load('mukurtu_manager')->getPermissions());
  }

  /**
   * A site without a Mukurtu Manager role is left alone.
   */
  public function testWithoutManagerRole(): void {
    mukurtu_core_update_40226();
    $this->assertNull(Role::load('mukurtu_manager'));
  }

}
