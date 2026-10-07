<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_export\Entity\CsvExporter;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_export_update_40402(), which adds the Language code column.
 *
 * As in CsvExporterDefaultsUpdateTest, the presets are put back into their
 * pre-fix state, or the hook would have nothing to do.
 *
 * @see mukurtu_export_update_40402()
 */
#[Group('mukurtu_export')]
class LanguageCodeColumnUpdateTest extends KernelTestBase {

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
   * The shipped presets.
   */
  private const IDS = [
    'default_local_metadata_only',
    'default_local_with_media',
    'default_external_metadata_only',
    'default_external_with_media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_export');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_export.install';

    $source = new FileStorage($module_path . '/config/install');
    foreach (self::IDS as $id) {
      $data = $source->read("mukurtu_export.csv_exporter.$id");
      $this->assertArrayHasKey('field_language_code', $data['entity_fields_export_list']['taxonomy_term__language'], "Precondition: $id does not ship the column.");
      unset($data['entity_fields_export_list']['taxonomy_term__language']['field_language_code']);
      CsvExporter::create($data)->save();
    }
  }

  /**
   * Loads a preset's language term columns.
   */
  private function columnsOf(string $id): array {
    $exporter = \Drupal::entityTypeManager()->getStorage('csv_exporter')->loadUnchanged($id);
    $this->assertNotNull($exporter, "The $id preset is not saved, so this test cannot mean anything.");
    return $exporter->get('entity_fields_export_list')['taxonomy_term__language'] ?? [];
  }

  /**
   * Tests that the column is appended to each preset, once.
   */
  public function testAddsColumn(): void {
    $this->assertSame('Added the Language code column to 4 default CSV export setting(s).', mukurtu_export_update_40402());

    foreach (self::IDS as $id) {
      $columns = $this->columnsOf($id);
      $this->assertSame('Language code', $columns['field_language_code'] ?? NULL, "$id did not get the column.");
      $this->assertSame('field_language_code', array_key_last($columns), "$id did not get the column at the end.");
    }

    $this->assertNull(mukurtu_export_update_40402(), 'A second run changed something.');
  }

  /**
   * Tests that a site's own choices are left alone.
   */
  public function testLeavesSiteChoicesAlone(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('csv_exporter');
    $storage->load('default_local_with_media')->delete();

    $exporter = $storage->load('default_external_with_media');
    $map = $exporter->get('entity_fields_export_list');
    unset($map['taxonomy_term__language']);
    $exporter->set('entity_fields_export_list', $map)->save();

    $exporter = $storage->load('default_external_metadata_only');
    $map = $exporter->get('entity_fields_export_list');
    $map['taxonomy_term__language']['field_language_code'] = 'ISO code';
    $exporter->set('entity_fields_export_list', $map)->save();

    $this->assertSame('Added the Language code column to 1 default CSV export setting(s).', mukurtu_export_update_40402());

    $this->assertNull($storage->loadUnchanged('default_local_with_media'), 'The deleted preset came back.');
    $this->assertSame([], $this->columnsOf('default_external_with_media'), 'The removed bundle came back.');
    $this->assertSame('ISO code', $this->columnsOf('default_external_metadata_only')['field_language_code']);
    $this->assertArrayHasKey('field_language_code', $this->columnsOf('default_local_metadata_only'));
  }

}
