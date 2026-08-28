<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\og\Entity\OgRole;

/**
 * Tests mukurtu_protocol_update_40041(), which grants 'view media' to
 * protocol roles that previously relied on a hardcoded role-ID list for
 * /admin/media access.
 *
 * @see mukurtu_protocol_update_40041()
 * @group mukurtu_protocol
 */
class ViewMediaPermissionUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
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
    'workflows',
  ];

  /**
   * The five roles the update hook should migrate.
   */
  protected const MIGRATED_ROLE_IDS = [
    'protocol-protocol-community_record_steward',
    'protocol-protocol-contributor',
    'protocol-protocol-language_contributor',
    'protocol-protocol-language_steward',
    'protocol-protocol-protocol_steward',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_protocol');
    require_once $module_path . '/mukurtu_protocol.install';
  }

  /**
   * Creates a protocol OG role with the given ID, without 'view media'.
   */
  protected function createProtocolRole(string $id): OgRole {
    $role = OgRole::create(['id' => $id, 'label' => $id]);
    $role->setGroupType('protocol');
    $role->setGroupBundle('protocol');
    $role->save();
    return $role;
  }

  /**
   * The update hook grants 'view media' to all five migrated roles.
   */
  public function testUpdateGrantsViewMediaToMigratedRoles(): void {
    foreach (self::MIGRATED_ROLE_IDS as $role_id) {
      $this->createProtocolRole($role_id);
    }

    mukurtu_protocol_update_40041();

    foreach (self::MIGRATED_ROLE_IDS as $role_id) {
      $role = OgRole::load($role_id);
      $this->assertTrue($role->hasPermission('view media'), "Expected '$role_id' to have view media.");
    }
  }

  /**
   * The update hook does not touch roles outside the migrated list.
   */
  public function testUpdateDoesNotTouchOtherRoles(): void {
    $this->createProtocolRole('protocol-protocol-protocol_affiliate');

    mukurtu_protocol_update_40041();

    $role = OgRole::load('protocol-protocol-protocol_affiliate');
    $this->assertFalse($role->hasPermission('view media'));
  }

  /**
   * The update hook is idempotent and a no-op for missing roles.
   */
  public function testUpdateSkipsMissingRoles(): void {
    $this->assertNull(OgRole::load('protocol-protocol-contributor'));
    // Should not throw despite none of the roles existing.
    mukurtu_protocol_update_40041();
    $this->assertNull(OgRole::load('protocol-protocol-contributor'));
  }

  /**
   * The update hook leaves a migrated role's other permissions untouched.
   */
  public function testUpdatePreservesExistingPermissions(): void {
    $role = $this->createProtocolRole('protocol-protocol-contributor');
    $role->grantPermission('apply protocol');
    $role->save();

    mukurtu_protocol_update_40041();

    $role = OgRole::load('protocol-protocol-contributor');
    $this->assertTrue($role->hasPermission('apply protocol'));
    $this->assertTrue($role->hasPermission('view media'));
  }

}
