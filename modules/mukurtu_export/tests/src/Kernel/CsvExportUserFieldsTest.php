<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_export\Entity\CsvExporter;

/**
 * Tests that the shipped default CSV exporter presets export user fields.
 *
 * Regression test for a round-trip testing issue: exporting users via any
 * of the four default presets previously produced a blank CSV, because none
 * of them shipped a user__user entry in entity_fields_export_list, so
 * CsvExporter::getHeaders()/getExportFields() returned an empty list for the
 * user entity type.
 *
 * @see \Drupal\mukurtu_export\Entity\CsvExporter::getHeaders()
 * @see \Drupal\mukurtu_export\Entity\CsvExporter::getExportFields()
 */
class CsvExportUserFieldsTest extends KernelTestBase {

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
   * The default csv_exporter preset IDs shipped by mukurtu_export.
   */
  protected const PRESET_IDS = [
    'default_local_metadata_only',
    'default_local_with_media',
    'default_external_metadata_only',
    'default_external_with_media',
  ];

  /**
   * Test that every default preset maps export fields for user/user.
   */
  public function testDefaultPresetsExportUserFields(): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_export');
    $source = new FileStorage($module_path . '/config/install');

    foreach (self::PRESET_IDS as $id) {
      $config_name = "mukurtu_export.csv_exporter.$id";
      $data = $source->read($config_name);
      $this->assertNotFalse($data, "$config_name should exist.");

      $exporter = CsvExporter::create($data);

      $headers = $exporter->getHeaders('user', 'user');
      $this->assertNotEmpty($headers, "$id must map export fields for user/user, or exporting users produces a blank CSV.");
      $this->assertArrayHasKey('name', $headers);
      $this->assertArrayHasKey('mail', $headers);
      $this->assertArrayHasKey('account_status', $headers);

      $fields = $exporter->getExportFields('user', 'user');
      $this->assertContains('name', $fields);
      $this->assertContains('mail', $fields);
      $this->assertContains('account_status', $fields);
    }
  }

}
