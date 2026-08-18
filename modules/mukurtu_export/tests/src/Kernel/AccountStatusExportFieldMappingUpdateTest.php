<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_export\Entity\CsvExporter;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_export_update_40019(), which replaces the separate Status/
 * Pending export columns with a single Account Status column in existing
 * sites' default CSV exporter presets.
 *
 * @see mukurtu_export_update_40019()
 */
#[Group('mukurtu_export')]
class AccountStatusExportFieldMappingUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'geofield',
    'leaflet',
    'mukurtu_core',
    'mukurtu_export',
    'mukurtu_multipage_items',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_export');
    require_once $module_path . '/mukurtu_export.install';
  }

  /**
   * The update hook replaces status/field_pending with account_status,
   * preserving the position status was in and every other field mapping.
   */
  public function testUpdateReplacesStatusAndPendingColumns(): void {
    CsvExporter::create([
      'id' => 'default_local_metadata_only',
      'label' => 'Default local settings (metadata only)',
      'entity_fields_export_list' => [
        'user__user' => [
          'name' => 'Username',
          'status' => 'Status',
          'field_pending' => 'Pending',
          'roles' => 'Roles',
        ],
      ],
    ])->save();

    mukurtu_export_update_40019();

    $exporter = CsvExporter::load('default_local_metadata_only');
    $headers = $exporter->getHeaders('user', 'user');
    $this->assertEquals(
      ['name' => 'Username', 'account_status' => 'Account Status', 'roles' => 'Roles'],
      $headers,
    );
  }

  /**
   * The update hook is a no-op for a preset already migrated to
   * account_status (e.g. one installed fresh with the new default).
   */
  public function testUpdateDoesNotOverwriteAlreadyMigratedMapping(): void {
    CsvExporter::create([
      'id' => 'default_local_metadata_only',
      'label' => 'Default local settings (metadata only)',
      'entity_fields_export_list' => [
        'user__user' => ['account_status' => 'Custom Account Status Label'],
      ],
    ])->save();

    mukurtu_export_update_40019();

    $exporter = CsvExporter::load('default_local_metadata_only');
    $this->assertEquals(['account_status' => 'Custom Account Status Label'], $exporter->getHeaders('user', 'user'));
  }

  /**
   * The update hook is a no-op for a preset with no user__user mapping at
   * all (predates mukurtu_export_update_40018()).
   */
  public function testUpdateIsNoOpForMissingUserMapping(): void {
    CsvExporter::create([
      'id' => 'default_local_metadata_only',
      'label' => 'Default local settings (metadata only)',
      'entity_fields_export_list' => [
        'node__article' => ['title' => 'Title'],
      ],
    ])->save();

    mukurtu_export_update_40019();

    $exporter = CsvExporter::load('default_local_metadata_only');
    $this->assertEquals(['title' => 'Title'], $exporter->getHeaders('node', 'article'));
    $this->assertEquals([], $exporter->getHeaders('user', 'user'));
  }

  /**
   * The update hook is a no-op for a preset that doesn't exist on this site.
   */
  public function testUpdateIsNoOpForMissingPreset(): void {
    $this->assertNull(CsvExporter::load('default_local_metadata_only'));
    mukurtu_export_update_40019();
    $this->assertNull(CsvExporter::load('default_local_metadata_only'));
  }

}
