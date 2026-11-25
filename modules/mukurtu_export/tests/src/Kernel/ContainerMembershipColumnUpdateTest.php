<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_export\Entity\CsvExporter;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_export_update_40403(), which adds the membership columns.
 *
 * As in LanguageCodeColumnUpdateTest, the presets are put back into their
 * pre-fix state, or the hook would have nothing to do.
 *
 * @see mukurtu_export_update_40403()
 */
#[Group('mukurtu_export')]
class ContainerMembershipColumnUpdateTest extends KernelTestBase {

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
   * The columns the hook adds, by bundle.
   */
  private const COLUMNS = [
    'node__digital_heritage' => ['field_in_collection' => 'Collections'],
    'node__dictionary_word' => [
      'field_in_collection' => 'Collections',
      'field_in_word_list' => 'Word Lists',
    ],
    'node__word_list' => ['field_in_collection' => 'Collections'],
    'node__person' => ['field_in_collection' => 'Collections'],
    'node__place' => ['field_in_collection' => 'Collections'],
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
      foreach (self::COLUMNS as $bundle => $columns) {
        foreach ($columns as $field_name => $label) {
          $this->assertSame($label, $data['entity_fields_export_list'][$bundle][$field_name] ?? NULL, "Precondition: $id does not ship $field_name for $bundle.");
          unset($data['entity_fields_export_list'][$bundle][$field_name]);
        }
      }
      CsvExporter::create($data)->save();
    }
  }

  /**
   * Loads one bundle's columns from a preset.
   */
  private function columnsOf(string $id, string $bundle): array {
    $exporter = \Drupal::entityTypeManager()->getStorage('csv_exporter')->loadUnchanged($id);
    $this->assertNotNull($exporter, "The $id preset is not saved, so this test cannot mean anything.");
    return $exporter->get('entity_fields_export_list')[$bundle] ?? [];
  }

  /**
   * The columns are appended to each preset, once, matching config/install.
   */
  public function testAddsColumns(): void {
    $this->assertSame('Added the Collections and Word Lists columns to 4 default CSV export setting(s).', mukurtu_export_update_40403());

    $shipped = new FileStorage(\Drupal::service('extension.list.module')->getPath('mukurtu_export') . '/config/install');
    foreach (self::IDS as $id) {
      $expected = $shipped->read("mukurtu_export.csv_exporter.$id")['entity_fields_export_list'];
      foreach (array_keys(self::COLUMNS) as $bundle) {
        $this->assertSame($expected[$bundle], $this->columnsOf($id, $bundle), "$id: $bundle doesn't match a new install.");
      }
    }

    $this->assertNull(mukurtu_export_update_40403(), 'A second run changed something.');
  }

  /**
   * A site's own choices are left alone.
   */
  public function testLeavesSiteChoicesAlone(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('csv_exporter');
    $storage->load('default_local_with_media')->delete();

    $exporter = $storage->load('default_external_with_media');
    $map = $exporter->get('entity_fields_export_list');
    unset($map['node__person']);
    $exporter->set('entity_fields_export_list', $map)->save();

    $exporter = $storage->load('default_external_metadata_only');
    $map = $exporter->get('entity_fields_export_list');
    $map['node__place']['field_in_collection'] = 'In collections';
    $exporter->set('entity_fields_export_list', $map)->save();

    $this->assertSame('Added the Collections and Word Lists columns to 3 default CSV export setting(s).', mukurtu_export_update_40403());

    $this->assertNull($storage->loadUnchanged('default_local_with_media'), 'The deleted preset came back.');
    $this->assertSame([], $this->columnsOf('default_external_with_media', 'node__person'), 'The removed bundle came back.');
    $this->assertSame('In collections', $this->columnsOf('default_external_metadata_only', 'node__place')['field_in_collection']);
    $this->assertSame('Collections', $this->columnsOf('default_external_with_media', 'node__place')['field_in_collection']);
  }

}
