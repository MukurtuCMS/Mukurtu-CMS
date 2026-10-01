<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the four shipped CSV export presets list the same columns.
 *
 * The local and external presets are meant to differ only in how an item is
 * identified (local ID versus UUID), and the metadata-only and with-media
 * presets only in how files are written, which is a top-level setting rather
 * than a column. Any other difference in a bundle's columns is drift: a column
 * added to one preset and forgotten in the others.
 */
#[Group('mukurtu_export')]
class CsvExporterDefaultsConsistencyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The default csv_exporter preset IDs shipped by mukurtu_export.
   */
  private const PRESET_IDS = [
    'default_local_metadata_only',
    'default_local_with_media',
    'default_external_metadata_only',
    'default_external_with_media',
  ];

  /**
   * Columns allowed to differ between presets, because they identify an item.
   */
  private const ID_COLUMNS = ['nid', 'mid', 'id', 'tid', 'fid', 'uuid'];

  /**
   * Columns allowed to differ, per bundle, for reasons other than identity.
   *
   * File timestamps are left out of the external presets on purpose: a site
   * importing the files gives them fresh timestamps.
   */
  private const ALLOWED_DIFFERENCES = [
    'file__file' => ['created', 'changed'],
  ];

  /**
   * Bundles without a default_langcode column.
   *
   * Users and files aren't translated, so there is no default translation to
   * record.
   */
  private const NO_DEFAULT_LANGCODE = ['file__file', 'user__user'];

  /**
   * Reads each shipped preset's column map, keyed by preset ID.
   */
  private function presetMaps(): array {
    $module_path = \Drupal::root() . '/' . \Drupal::service('extension.list.module')->getPath('mukurtu_export');
    $maps = [];
    foreach (self::PRESET_IDS as $id) {
      $file = "$module_path/config/install/mukurtu_export.csv_exporter.$id.yml";
      $this->assertFileExists($file);
      $maps[$id] = Yaml::decode(file_get_contents($file))['entity_fields_export_list'];
    }
    return $maps;
  }

  /**
   * Tests that every preset lists the same bundles and columns.
   */
  public function testPresetsListTheSameColumns(): void {
    $maps = $this->presetMaps();

    $bundles = array_unique(array_merge(...array_map('array_keys', array_values($maps))));
    $this->assertNotEmpty($bundles);

    foreach ($bundles as $bundle) {
      $ignored = array_merge(self::ID_COLUMNS, self::ALLOWED_DIFFERENCES[$bundle] ?? []);
      $columns = [];
      foreach ($maps as $id => $map) {
        $this->assertArrayHasKey($bundle, $map, "The $id preset does not list $bundle.");
        $columns[$id] = array_values(array_diff(array_keys($map[$bundle]), $ignored));
        sort($columns[$id]);
      }

      $expected = reset($columns);
      foreach ($columns as $id => $actual) {
        $this->assertSame($expected, $actual, sprintf(
          'The %s columns in %s differ from %s. Missing: [%s]. Extra: [%s].',
          $bundle,
          $id,
          self::PRESET_IDS[0],
          implode(', ', array_diff($expected, $actual)),
          implode(', ', array_diff($actual, $expected)),
        ));
      }
    }
  }

  /**
   * Tests that every translatable bundle exports its default translation.
   */
  public function testTranslatableBundlesExportDefaultTranslation(): void {
    foreach ($this->presetMaps() as $id => $map) {
      foreach ($map as $bundle => $columns) {
        if (in_array($bundle, self::NO_DEFAULT_LANGCODE, TRUE)) {
          continue;
        }
        $this->assertArrayHasKey('default_langcode', $columns, "The $bundle columns in $id have no default_langcode.");
      }
    }
  }

}
