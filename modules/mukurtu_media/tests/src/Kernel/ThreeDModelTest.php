<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\mukurtu_media\Entity\ThreeDModel;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 3D Model media type (#336).
 *
 * @see \Drupal\mukurtu_media\Entity\ThreeDModel
 * @see \Drupal\mukurtu_media\EventSubscriber\ThreeDModelMimeTypeSubscriber
 * @see mukurtu_v4_preprocess_media__3d_model__media_assets()
 */
#[Group('mukurtu_media')]
class ThreeDModelTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'workflows',
    'field',
    'file',
    'filter',
    'geofield',
    'image',
    'key',
    'leaflet',
    'node',
    'node_access_test',
    'media',
    'media_library',
    'og',
    'options',
    'system',
    'text',
    'taxonomy',
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

    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);

    // The shipped media type, so the test covers its source field too.
    $path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    MediaType::create(Yaml::decode(file_get_contents("$path/config/install/media.type.3d_model.yml")))->save();

    require_once \Drupal::root() . '/' . \Drupal::service('extension.list.theme')->getPath('mukurtu_v4') . '/mukurtu_v4.theme';
  }

  /**
   * Creates a small image file to use as a thumbnail.
   */
  protected function createImage(string $filename): File {
    $directory = 'public://';
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $file = File::create(['uri' => 'public://' . $filename, 'filename' => $filename, 'status' => 1]);
    file_put_contents($file->getFileUri(), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
    $file->save();
    return $file;
  }

  /**
   * The bundle gets its class, a GLB source field and an optional USDZ field.
   */
  public function testBundleClassAndFields(): void {
    $media = Media::create(['bundle' => '3d_model', 'name' => 'Basket']);
    $this->assertInstanceOf(ThreeDModel::class, $media);
    $this->assertSame('field_media_3d_model', $media->getSource()->getConfiguration()['source_field']);

    $model = $media->getFieldDefinition('field_media_3d_model');
    $this->assertSame('file', $model->getType());
    $this->assertSame('glb', $model->getSetting('file_extensions'));
    $this->assertSame('private', $model->getSetting('uri_scheme'));
    $this->assertTrue($model->isRequired());

    $usdz = $media->getFieldDefinition('field_media_3d_model_usdz');
    $this->assertSame('usdz', $usdz->getSetting('file_extensions'));
    $this->assertSame('private', $usdz->getSetting('uri_scheme'));
    $this->assertFalse($usdz->isRequired());

    foreach (['field_thumbnail', 'field_media_tags', 'field_people', 'field_identifier', 'field_cultural_protocols'] as $field_name) {
      $this->assertTrue($media->hasField($field_name), $field_name);
    }
  }

  /**
   * GLB and USDZ files get real MIME types, which core doesn't know.
   */
  public function testMimeTypes(): void {
    $guesser = \Drupal::service('file.mime_type.guesser');
    $this->assertSame('model/gltf-binary', $guesser->guessMimeType('basket.glb'));
    $this->assertSame('model/vnd.usdz+zip', $guesser->guessMimeType('basket.usdz'));
  }

  /**
   * An uploaded thumbnail is used, with the name as its alt text.
   */
  public function testUploadedThumbnail(): void {
    $image = $this->createImage('basket.png');
    $media = Media::create([
      'bundle' => '3d_model',
      'name' => 'Woven basket',
      'field_thumbnail' => ['target_id' => $image->id()],
    ]);
    $media->save();

    $this->assertEquals($image->id(), $media->get('thumbnail')->target_id);
    $this->assertSame('Woven basket', $media->get('field_thumbnail')->alt);
  }

  /**
   * Without an uploaded thumbnail, the site's default 3D model one is used.
   */
  public function testDefaultThumbnail(): void {
    $image = $this->createImage('default-3d.png');
    $this->config('mukurtu_media.thumbnail_settings')->set('3d_model', [(int) $image->id()])->save();

    $media = Media::create(['bundle' => '3d_model', 'name' => 'Canoe']);
    $media->save();

    $this->assertEquals($image->id(), $media->get('thumbnail')->target_id);
    $this->assertTrue($media->get('field_thumbnail')->isEmpty());
  }

  /**
   * The viewer gets translated announcements, a poster and local decoders.
   */
  public function testMediaAssetsPreprocess(): void {
    $image = $this->createImage('poster.png');
    $media = Media::create([
      'bundle' => '3d_model',
      'name' => 'Paddle',
      'field_thumbnail' => ['target_id' => $image->id()],
    ]);
    $media->save();

    $variables = ['media' => $media];
    mukurtu_v4_preprocess_media__3d_model__media_assets($variables);

    $this->assertNull($variables['model_usdz_url']);
    $this->assertStringEndsWith('/poster.png', $variables['model_poster_url']);

    $a11y = json_decode($variables['model_a11y'], TRUE);
    $positions = ['front', 'right', 'back', 'left'];
    foreach (['', 'upper-', 'lower-'] as $prefix) {
      foreach ($positions as $position) {
        $this->assertArrayHasKey($prefix . $position, $a11y);
      }
    }
    $this->assertSame('View from above the back', $a11y['upper-back']);
    $this->assertArrayHasKey('interaction-prompt', $a11y);

    $this->assertContains('mukurtu_v4/media-3d-model', $variables['#attached']['library']);
    $settings = $variables['#attached']['drupalSettings']['mukurtuModelViewer'];
    $this->assertStringEndsWith('/libraries/model-viewer/draco/', $settings['dracoDecoderLocation']);
    $this->assertStringEndsWith('/libraries/model-viewer/basis/', $settings['ktx2TranscoderLocation']);
    $this->assertStringNotContainsString('gstatic', $settings['dracoDecoderLocation'] . $settings['ktx2TranscoderLocation']);
  }

  /**
   * Core's generic file icon isn't used as the viewer's poster.
   */
  public function testGenericIconIsNotUsedAsPoster(): void {
    // With no uploaded or default thumbnail, the file source sets its icon.
    $media = Media::create(['bundle' => '3d_model', 'name' => 'Drum']);
    $media->save();
    $this->assertSame($media->getSource()->getMetadata($media, 'thumbnail_uri'), $media->get('thumbnail')->entity->getFileUri());

    $variables = ['media' => $media];
    mukurtu_v4_preprocess_media__3d_model__media_assets($variables);

    $this->assertNull($variables['model_poster_url']);
  }

}
