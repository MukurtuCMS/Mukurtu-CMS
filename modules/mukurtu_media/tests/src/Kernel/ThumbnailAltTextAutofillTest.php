<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\Core\Serialization\Yaml;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests thumbnail alt text auto-fill on preSave() for embed-based media.
 *
 * @see \Drupal\mukurtu_media\Entity\ExternalEmbed::preSave()
 * @see \Drupal\mukurtu_media\Entity\RemoteVideo::preSave()
 * @see \Drupal\mukurtu_media\Entity\SoundCloud::preSave()
 */
#[Group('mukurtu_media')]
class ThumbnailAltTextAutofillTest extends KernelTestBase {

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
    $this->installEntitySchema('node');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');
    $this->installConfig([
      'field',
      'system',
      'image',
      'file',
      'media',
      'filter',
    ]);

    // Create the real production bundle definitions from mukurtu_core
    // directly, rather than installing all of mukurtu_core's config (which
    // pulls in unrelated content types/fields requiring modules like
    // geofield, layout_builder, and comment that this test doesn't need).
    $mukurtuCorePath = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    foreach (['external_embed', 'remote_video', 'soundcloud'] as $bundle) {
      $definition = Yaml::decode(file_get_contents("$mukurtuCorePath/config/install/media.type.$bundle.yml"));
      MediaType::create($definition)->save();
    }

    $this->installEntitySchema('media');

    User::create(['uid' => 1, 'name' => 'uid1-placeholder', 'status' => 1])->save();
  }

  /**
   * Creates a thumbnail file entity for use in a media entity.
   */
  protected function createThumbnailFile(): File {
    $file = File::create([
      'uri' => 'public://thumbnail.jpg',
      'filename' => 'thumbnail.jpg',
      'status' => 1,
    ]);
    $file->save();
    return $file;
  }

  /**
   * Tests that saving without alt text fills it in from the media name.
   */
  #[DataProvider('bundleProvider')]
  public function testThumbnailAltTextIsAutoFilled(string $bundle): void {
    $thumbnail = $this->createThumbnailFile();

    $media = Media::create([
      'bundle' => $bundle,
      'name' => 'Test ' . $bundle . ' asset',
      'field_thumbnail' => [
        'target_id' => $thumbnail->id(),
      ],
    ]);
    $media->save();

    $media = Media::load($media->id());
    $this->assertSame('Test ' . $bundle . ' asset', $media->get('field_thumbnail')->alt);
  }

  /**
   * Tests that a manually-entered alt value is not overwritten on save.
   */
  #[DataProvider('bundleProvider')]
  public function testExistingThumbnailAltTextIsPreserved(string $bundle): void {
    $thumbnail = $this->createThumbnailFile();

    $media = Media::create([
      'bundle' => $bundle,
      'name' => 'Test ' . $bundle . ' asset',
      'field_thumbnail' => [
        'target_id' => $thumbnail->id(),
        'alt' => 'User provided alt text',
      ],
    ]);
    $media->save();

    $media = Media::load($media->id());
    $this->assertSame('User provided alt text', $media->get('field_thumbnail')->alt);
  }

  /**
   * Data provider of the media bundles covered by the alt-text auto-fill.
   */
  public static function bundleProvider(): array {
    return [
      'external_embed' => ['external_embed'],
      'remote_video' => ['remote_video'],
      'soundcloud' => ['soundcloud'],
    ];
  }

}
