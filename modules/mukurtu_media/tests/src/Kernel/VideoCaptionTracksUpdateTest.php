<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\MediaType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_media_update_40024(), which adds caption tracks to video.
 *
 * @see mukurtu_media_update_40024()
 */
#[Group('mukurtu_media')]
class VideoCaptionTracksUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'filter',
    'geofield',
    'image',
    'key',
    'leaflet',
    'media',
    'media_entity_soundcloud',
    'media_library',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'mukurtu_core',
    'mukurtu_protocol',
    'mukurtu_media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['field', 'system', 'image', 'file', 'media', 'filter']);

    $mukurtu_core_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    $definition = Yaml::decode(file_get_contents("$mukurtu_core_path/config/install/media.type.video.yml"));
    MediaType::create($definition)->save();
    $this->installEntitySchema('media');

    // Roll the site back to before the update: no track storage installed.
    $manager = \Drupal::entityDefinitionUpdateManager();
    $manager->uninstallFieldStorageDefinition($manager->getFieldStorageDefinition('field_media_video_tracks', 'media'));

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_media');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_media.install';
  }

  /**
   * Creates a video view display showing the file with the given formatter.
   */
  protected function createViewDisplay(string $mode, string $formatter): EntityViewDisplay {
    EntityViewMode::create(['id' => "media.$mode", 'targetEntityType' => 'media', 'label' => $mode])->save();
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'media',
      'bundle' => 'video',
      'mode' => $mode,
      'status' => TRUE,
    ]);
    $display->setComponent('field_media_video_file', [
      'type' => $formatter,
      'label' => 'hidden',
      'settings' => ['width' => 250, 'height' => 141],
      'weight' => 3,
    ])->save();
    return $display;
  }

  /**
   * The update installs the field, adds the widget, and switches formatters.
   */
  public function testUpdate(): void {
    $manager = \Drupal::entityDefinitionUpdateManager();
    $this->assertNull($manager->getFieldStorageDefinition('field_media_video_tracks', 'media'));

    EntityFormDisplay::create([
      'targetEntityType' => 'media',
      'bundle' => 'video',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_media_video_file', ['type' => 'file_generic', 'weight' => 0])->save();
    $this->createViewDisplay('small', 'file_video');
    $this->createViewDisplay('large', 'file_default');

    mukurtu_media_update_40024();
    // Running it twice is harmless.
    mukurtu_media_update_40024();

    $storage = $manager->getFieldStorageDefinition('field_media_video_tracks', 'media');
    $this->assertSame('mukurtu_video_track', $storage?->getType());
    $this->assertSame('mukurtu_media', $storage->getProvider());
    $this->assertArrayNotHasKey('media', $manager->getChangeSummary(), 'Media storage matches its definitions after the update.');

    $form = EntityFormDisplay::load('media.video.default');
    $this->assertSame('mukurtu_video_track', $form->getComponent('field_media_video_tracks')['type'] ?? NULL);

    $small = EntityViewDisplay::load('media.video.small');
    $component = $small->getComponent('field_media_video_file');
    $this->assertSame('mukurtu_video_with_tracks', $component['type']);
    $this->assertSame(250, $component['settings']['width'], 'Formatter settings carry over.');
    $this->assertSame(3, $component['weight']);
    $this->assertNull($small->getComponent('field_media_video_tracks'));

    // A display an admin moved to another formatter is left alone.
    $large = EntityViewDisplay::load('media.video.large');
    $this->assertSame('file_default', $large->getComponent('field_media_video_file')['type']);
    $this->assertNull($large->getComponent('field_media_video_tracks'));
  }

}
