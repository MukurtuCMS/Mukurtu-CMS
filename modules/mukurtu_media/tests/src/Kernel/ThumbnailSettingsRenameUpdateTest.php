<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigImporterException;
use Drupal\Core\Config\StorageComparer;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_media_update_40025().
 *
 * The default thumbnail settings were stored as mukurtu_thumbnail.settings.
 * No "mukurtu_thumbnail" extension exists, so configuration import
 * validation rejected them, and with them every import on the site.
 */
#[Group('mukurtu_media')]
class ThumbnailSettingsRenameUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'flag',
    'geofield',
    'image',
    'key',
    'leaflet',
    'media',
    'media_entity_soundcloud',
    'media_library',
    'mukurtu_core',
    'mukurtu_media',
    'mukurtu_protocol',
    'node',
    'og',
    'options',
    'path',
    'system',
    'tagify',
    'taxonomy',
    'text',
    'user',
    'views',
    'views_bulk_operations',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Import validation needs the site's own settings.
    $this->installConfig(['system']);
    require_once $this->root . '/' . \Drupal::service('extension.list.module')->getPath('mukurtu_media') . '/mukurtu_media.install';
  }

  /**
   * Returns the errors from validating an import of the site's own config.
   */
  protected function importErrors(): array {
    $active = $this->container->get('config.storage');
    $importer = new ConfigImporter(
      (new StorageComparer($active, $active))->createChangelist(),
      $this->container->get('event_dispatcher'),
      $this->container->get('config.manager'),
      $this->container->get('lock'),
      $this->container->get('config.typed'),
      $this->container->get('module_handler'),
      $this->container->get('module_installer'),
      $this->container->get('theme_handler'),
      $this->container->get('string_translation'),
      $this->container->get('extension.list.module'),
      $this->container->get('extension.list.theme'),
    );
    try {
      $importer->validate();
      return [];
    }
    catch (ConfigImporterException) {
      return array_map('strval', $importer->getErrors());
    }
  }

  /**
   * The settings move, the old name goes, and imports validate again.
   */
  public function testRename(): void {
    // As an existing site stores them, with no schema for the old name.
    $this->container->get('config.storage')->write('mukurtu_thumbnail.settings', [
      'audio_default_thumbnail' => ['7'],
      'video_default_thumbnail' => ['8'],
    ]);
    $this->container->get('config.factory')->reset('mukurtu_thumbnail.settings');
    $this->assertStringContainsString('mukurtu_thumbnail', implode(' ', $this->importErrors()));

    $this->assertSame('Moved the default media thumbnail settings to mukurtu_media.thumbnail_settings.', mukurtu_media_update_40025());

    $this->assertFalse($this->container->get('config.storage')->exists('mukurtu_thumbnail.settings'));
    $settings = $this->container->get('config.factory')->get('mukurtu_media.thumbnail_settings');
    // The schema casts the file IDs on save.
    $this->assertSame([7], $settings->get('audio_default_thumbnail'));
    $this->assertSame([8], $settings->get('video_default_thumbnail'));
    $this->assertSame([], $this->importErrors());

    $this->assertSame('The default media thumbnail settings are already up to date; nothing to change.', mukurtu_media_update_40025());
  }

  /**
   * Values a site already saved under the new name are kept.
   */
  public function testKeepsNewValues(): void {
    $this->container->get('config.factory')->getEditable('mukurtu_media.thumbnail_settings')
      ->set('audio_default_thumbnail', [9])
      ->save();
    $this->container->get('config.storage')->write('mukurtu_thumbnail.settings', [
      'audio_default_thumbnail' => [7],
      'video_default_thumbnail' => [8],
    ]);
    $this->container->get('config.factory')->reset('mukurtu_thumbnail.settings');

    mukurtu_media_update_40025();

    $settings = $this->container->get('config.factory')->get('mukurtu_media.thumbnail_settings');
    $this->assertSame([9], $settings->get('audio_default_thumbnail'));
    $this->assertSame([8], $settings->get('video_default_thumbnail'));
  }

}
