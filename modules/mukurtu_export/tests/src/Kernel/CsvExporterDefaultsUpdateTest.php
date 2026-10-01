<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_export\Entity\CsvExporter;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_export_update_40401(), which fills gaps in the CSV presets.
 *
 * The presets are deliberately put back into their pre-fix state here.
 * config/install now ships the missing columns, so a freshly saved preset
 * already has them and the hook would have nothing to do: every assertion
 * would pass without the hook being exercised at all.
 *
 * @see mukurtu_export_update_40401()
 */
#[Group('mukurtu_export')]
class CsvExporterDefaultsUpdateTest extends KernelTestBase {

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
   * The columns the hook adds, as preset ID => bundle => field name.
   */
  private const ADDED = [
    'default_local_metadata_only' => [
      'node__dictionary_word' => 'default_langcode',
      'media__external_embed' => 'field_found_in',
      'media__image' => 'default_langcode',
      'media__soundcloud' => 'default_langcode',
    ],
    'default_local_with_media' => [
      'node__dictionary_word' => 'default_langcode',
      'media__image' => 'default_langcode',
      'media__soundcloud' => 'default_langcode',
    ],
    'default_external_metadata_only' => [
      'node__article' => 'field_multipage_page_of',
      'node__dictionary_word' => 'default_langcode',
      'media__image' => 'default_langcode',
      'media__soundcloud' => 'default_langcode',
    ],
    'default_external_with_media' => [
      'node__dictionary_word' => 'default_langcode',
      'media__image' => 'default_langcode',
      'media__soundcloud' => 'default_langcode',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_export');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_export.install';

    // Save the four shipped presets from their install config, rather than
    // installing all of mukurtu_export's config (which would also pull in
    // config depending on modules, like flag, this test doesn't enable). Then
    // strip the columns the hook adds, to recreate an existing site.
    $source = new FileStorage($module_path . '/config/install');
    foreach (self::ADDED as $id => $bundles) {
      $data = $source->read("mukurtu_export.csv_exporter.$id");
      $this->assertNotFalse($data, "mukurtu_export.csv_exporter.$id should exist.");
      foreach ($bundles as $bundle => $field_name) {
        $this->assertArrayHasKey($field_name, $data['entity_fields_export_list'][$bundle], "Precondition: $id does not ship $bundle/$field_name.");
        unset($data['entity_fields_export_list'][$bundle][$field_name]);
      }
      CsvExporter::create($data)->save();
    }
  }

  /**
   * Loads a preset's column map for one bundle.
   */
  private function columnsOf(string $id, string $bundle): array {
    $exporter = \Drupal::entityTypeManager()->getStorage('csv_exporter')->loadUnchanged($id);
    $this->assertNotNull($exporter, "The $id preset is not saved, so this test cannot mean anything.");
    return $exporter->get('entity_fields_export_list')[$bundle] ?? [];
  }

  /**
   * Tests that the hook adds each missing column, at the end, once.
   */
  public function testAddsMissingColumns(): void {
    foreach (self::ADDED as $id => $bundles) {
      foreach ($bundles as $bundle => $field_name) {
        $this->assertArrayNotHasKey($field_name, $this->columnsOf($id, $bundle), "Precondition: $id $bundle/$field_name was not removed.");
      }
    }

    $message = mukurtu_export_update_40401();
    $this->assertSame('Added missing columns to 4 default CSV export setting(s).', $message);

    foreach (self::ADDED as $id => $bundles) {
      foreach ($bundles as $bundle => $field_name) {
        $columns = $this->columnsOf($id, $bundle);
        $this->assertArrayHasKey($field_name, $columns, "$id $bundle/$field_name was not added.");
        $this->assertSame($field_name, array_key_last($columns), "$id $bundle/$field_name was not appended at the end.");
      }
    }

    $this->assertNull(mukurtu_export_update_40401(), 'A second run changed something.');
  }

  /**
   * Tests that the hook leaves a site's own choices alone.
   */
  public function testLeavesSiteChoicesAlone(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('csv_exporter');

    // A site deleted one preset, dropped a bundle from another, and relabelled
    // a column in a third.
    $storage->load('default_local_with_media')->delete();

    $exporter = $storage->load('default_external_with_media');
    $map = $exporter->get('entity_fields_export_list');
    unset($map['media__image']);
    $exporter->set('entity_fields_export_list', $map)->save();

    $exporter = $storage->load('default_external_metadata_only');
    $map = $exporter->get('entity_fields_export_list');
    $map['node__article']['field_multipage_page_of'] = 'Parent page';
    $exporter->set('entity_fields_export_list', $map)->save();

    // A site-authored exporter missing the same column is not ours to edit.
    CsvExporter::create([
      'id' => 'custom_setting',
      'label' => 'Custom setting',
      'site_wide' => TRUE,
      'entity_fields_export_list' => [
        'media__image' => ['mid' => 'ID', 'name' => 'Name'],
      ],
    ])->save();

    mukurtu_export_update_40401();

    $this->assertNull($storage->loadUnchanged('default_local_with_media'), 'The deleted preset came back.');
    $this->assertSame([], $this->columnsOf('default_external_with_media', 'media__image'), 'The removed bundle came back.');
    $this->assertSame('Parent page', $this->columnsOf('default_external_metadata_only', 'node__article')['field_multipage_page_of']);
    $this->assertSame(['mid' => 'ID', 'name' => 'Name'], $this->columnsOf('custom_setting', 'media__image'));

    // The rest of the partly customised presets still got their columns.
    $this->assertArrayHasKey('default_langcode', $this->columnsOf('default_external_with_media', 'media__soundcloud'));
    $this->assertArrayHasKey('default_langcode', $this->columnsOf('default_external_metadata_only', 'media__image'));
  }

}
