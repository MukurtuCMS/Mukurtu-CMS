<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_export\Entity\CsvExporter;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_export_update_40018(), which backfills the user__user export
 * field mapping into existing sites' default CSV exporter presets.
 *
 * @see mukurtu_export_update_40018()
 */
#[Group('mukurtu_export')]
class UserExportFieldMappingUpdateTest extends KernelTestBase {

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
   * The update hook backfills a user__user mapping into a preset that
   * predates it, simulating an existing site that installed before this
   * field mapping shipped.
   */
  public function testUpdateBackfillsMissingMapping(): void {
    CsvExporter::create([
      'id' => 'default_local_metadata_only',
      'label' => 'Default local settings (metadata only)',
      'entity_fields_export_list' => [
        'node__article' => ['title' => 'Title'],
      ],
    ])->save();

    mukurtu_export_update_40018();

    $exporter = CsvExporter::load('default_local_metadata_only');
    $headers = $exporter->getHeaders('user', 'user');
    $this->assertNotEmpty($headers, 'The update hook must backfill a user__user field mapping.');
    $this->assertArrayHasKey('name', $headers);
    $this->assertArrayHasKey('mail', $headers);

    // Pre-existing mappings for other bundles must be untouched.
    $this->assertEquals(['title' => 'Title'], $exporter->getHeaders('node', 'article'));
  }

  /**
   * The update hook does not overwrite a preset that already has a mapping.
   */
  public function testUpdateDoesNotOverwriteExistingMapping(): void {
    CsvExporter::create([
      'id' => 'default_local_metadata_only',
      'label' => 'Default local settings (metadata only)',
      'entity_fields_export_list' => [
        'user__user' => ['name' => 'Custom Username Label'],
      ],
    ])->save();

    mukurtu_export_update_40018();

    $exporter = CsvExporter::load('default_local_metadata_only');
    $this->assertEquals(['name' => 'Custom Username Label'], $exporter->getHeaders('user', 'user'));
  }

  /**
   * The update hook is a no-op for a preset that doesn't exist on this site.
   */
  public function testUpdateIsNoOpForMissingPreset(): void {
    $this->assertNull(CsvExporter::load('default_local_metadata_only'));
    mukurtu_export_update_40018();
    $this->assertNull(CsvExporter::load('default_local_metadata_only'));
  }

}
