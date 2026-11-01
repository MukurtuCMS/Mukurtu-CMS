<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40034().
 *
 * Leaflet only adds the address search to the map widget for users with
 * "access geocoder api endpoints", and no Mukurtu role shipped with it, so
 * only administrators could search. The hook grants it to logged-in users.
 */
#[Group('mukurtu')]
class GeocoderPermissionUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['geocoder', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once $this->root . '/' . \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/mukurtu.install';
    Role::create(['id' => 'authenticated', 'label' => 'Authenticated user'])->grantPermission('access content')->save();
  }

  /**
   * Logged-in users gain the permission and keep their existing ones.
   */
  public function testGrantsPermission(): void {
    $message = mukurtu_update_40034();

    $role = Role::load('authenticated');
    $this->assertTrue($role->hasPermission('access geocoder api endpoints'));
    $this->assertTrue($role->hasPermission('access content'));
    $this->assertStringContainsString('can now search', $message);
  }

  /**
   * Running twice is a no-op the second time.
   */
  public function testIsIdempotent(): void {
    mukurtu_update_40034();
    $message = mukurtu_update_40034();

    $this->assertTrue(Role::load('authenticated')->hasPermission('access geocoder api endpoints'));
    $this->assertStringContainsString('nothing to change', $message);
  }

  /**
   * Fresh installs ship the same permission, so install and update agree.
   */
  public function testShippedRoleHasPermission(): void {
    $profile_path = \Drupal::service('extension.list.profile')->getPath('mukurtu');
    $data = (new FileStorage($profile_path . '/config/install'))->read('user.role.authenticated');

    $this->assertContains('access geocoder api endpoints', $data['permissions']);
    $this->assertContains('geocoder', $data['dependencies']['module']);
  }

}
