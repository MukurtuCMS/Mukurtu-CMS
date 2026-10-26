<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\StaleConfigKeyCleanup;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the mukurtu_thumbnail.settings schema.
 *
 * ThumbnailSettingsForm stores one flat key per media bundle (suffixed for
 * audio and video, see getConfigKey()), and older sites still have the bare
 * audio and video keys (#2386).
 */
#[Group('mukurtu_media')]
class ThumbnailSettingsSchemaTest extends KernelTestBase {

  use SchemaCheckTrait;

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
   * Every key the form and the installer write matches the schema.
   */
  public function testFormKeysMatchSchema(): void {
    $data = [];
    $keys = [
      'audio_default_thumbnail',
      'video_default_thumbnail',
      'document',
      'image',
      'external_embed',
      'remote_video',
      'soundcloud',
      'audio',
      'video',
    ];
    foreach ($keys as $key) {
      $data[$key] = [7];
    }
    $result = $this->checkConfigSchema(\Drupal::service('config.typed'), 'mukurtu_thumbnail.settings', $data);
    $this->assertTrue($result, is_array($result) ? implode("\n", $result) : 'No schema');
  }

  /**
   * The update cleanup casts file IDs the installer stored as strings.
   */
  public function testCleanupCastsStoredFileIds(): void {
    \Drupal::service('config.storage')->write('mukurtu_thumbnail.settings', [
      'audio_default_thumbnail' => ['3'],
      'video_default_thumbnail' => [4],
    ]);
    \Drupal::configFactory()->reset('mukurtu_thumbnail.settings');

    (new StaleConfigKeyCleanup(\Drupal::entityTypeManager(), \Drupal::configFactory()))->castThumbnailSettings();

    $data = \Drupal::service('config.storage')->read('mukurtu_thumbnail.settings');
    $this->assertSame([3], $data['audio_default_thumbnail']);
    $this->assertSame([4], $data['video_default_thumbnail']);
  }

}
