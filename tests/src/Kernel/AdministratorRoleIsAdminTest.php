<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Guards the assumption mukurtu_install() relies on: the administrator role
 * ships with is_admin: true.
 *
 * mukurtu_install() deliberately grants the administrator role no permissions,
 * because core's Role::grantPermission() returns early for admin roles and so
 * nothing it listed was ever stored. That is only safe while the shipped role
 * really is an admin role. If anyone flips is_admin to FALSE, administrator
 * would silently end up with no permissions at all on a fresh install, and this
 * test is what catches it.
 *
 * @see mukurtu_install()
 * @see \Drupal\user\Entity\Role::grantPermission()
 */
#[Group('mukurtu')]
class AdministratorRoleIsAdminTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * Returns the profile's shipped copy of a config file.
   */
  protected function readShippedConfig(string $name): array {
    // This test file lives at <profile>/tests/src/Kernel.
    $file = dirname(__DIR__, 3) . '/config/install/' . $name . '.yml';
    $this->assertFileExists($file);

    return Yaml::decode(file_get_contents($file));
  }

  /**
   * The shipped administrator role is an admin role.
   */
  public function testShippedAdministratorRoleIsAdmin(): void {
    $role = $this->readShippedConfig('user.role.administrator');

    $this->assertArrayHasKey('is_admin', $role);
    $this->assertTrue(
      $role['is_admin'],
      'user.role.administrator must ship with is_admin: TRUE. mukurtu_install() grants it no permissions on the strength of that flag, so turning it off would leave administrators with no permissions on a fresh install.'
    );
  }

  /**
   * The shipped administrator role carries no explicit permissions.
   *
   * An admin role stores none, so a non-empty list here would be dead weight
   * that reads as authoritative, which is exactly what was removed from
   * mukurtu_install().
   */
  public function testShippedAdministratorRoleHasNoExplicitPermissions(): void {
    $role = $this->readShippedConfig('user.role.administrator');

    $this->assertSame([], $role['permissions'] ?? []);
  }

  /**
   * Core still discards permissions granted to an admin role.
   *
   * The removal of the administrator list from mukurtu_install() rests on this
   * core behaviour, so assert it directly rather than trusting it to hold.
   */
  public function testGrantPermissionIsDiscardedOnAdminRoles(): void {
    $role = Role::create([
      'id' => 'test_admin',
      'label' => 'Test admin',
      'is_admin' => TRUE,
    ]);
    $role->grantPermission('administer nodes');
    $role->save();

    $saved = Role::load('test_admin');
    $this->assertSame([], $saved->getPermissions());
    // It still reports every permission as held.
    $this->assertTrue($saved->hasPermission('administer nodes'));
    $this->assertTrue($saved->hasPermission('some permission that does not exist'));
  }

}
