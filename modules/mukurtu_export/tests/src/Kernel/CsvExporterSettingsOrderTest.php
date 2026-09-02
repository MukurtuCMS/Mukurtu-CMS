<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_export\Entity\CsvExporter;

/**
 * Tests the display order of site-wide CSV export settings.
 *
 * The four shipped default presets must always be listed local-before-
 * external, ahead of any other site-wide setting an admin creates.
 *
 * @see \Drupal\mukurtu_export\Plugin\MukurtuExporter\CSV::addSiteWideConfigOptions()
 */
class CsvExporterSettingsOrderTest extends KernelTestBase {

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
   * Tests that the shipped defaults sort local-first, and other site-wide
   * settings still fall back to alphabetical order after them.
   */
  public function testDefaultPresetsSortLocalBeforeExternal(): void {
    // Save the four shipped default presets from their install config,
    // rather than installing all of mukurtu_export's config (which would
    // also pull in config depending on modules, like flag, this test
    // doesn't enable).
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_export');
    $source = new FileStorage($module_path . '/config/install');
    foreach (self::PRESET_IDS as $id) {
      $data = $source->read("mukurtu_export.csv_exporter.$id");
      $this->assertNotFalse($data, "mukurtu_export.csv_exporter.$id should exist.");
      CsvExporter::create($data)->save();
    }

    // A custom site-wide setting whose label would otherwise sort
    // alphabetically ahead of all four defaults, to confirm it still lands
    // after them rather than jumping the explicit order.
    CsvExporter::create([
      'id' => 'aaa_custom_setting',
      'label' => 'AAA Custom Setting',
      'site_wide' => TRUE,
    ])->save();

    $plugin = \Drupal::service('plugin.manager.mukurtu_exporter')->createInstance('csv');
    $form = $plugin->settingsForm([], new FormState());

    $expected = array_merge(self::PRESET_IDS, ['aaa_custom_setting']);
    $this->assertSame($expected, array_keys($form['export_settings']['#options']));
    $this->assertSame('default_local_metadata_only', $form['export_settings']['#default_value']);
  }

}
