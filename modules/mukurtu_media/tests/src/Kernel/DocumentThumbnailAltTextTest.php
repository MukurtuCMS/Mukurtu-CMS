<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that document thumbnails render with alt text (issue #1995).
 *
 * mukurtu_v4_preprocess_media__document__carousel_thumbnail() lives in the
 * theme (not a module), so it's exercised directly here rather than through
 * the full entity render pipeline, which is out of scope for a Kernel test.
 */
#[Group('mukurtu_media')]
class DocumentThumbnailAltTextTest extends ProtocolAwareEntityTestBase {

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
    'language',
  ];

  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);

    MediaType::create([
      'id' => 'document',
      'label' => 'Document',
      'source' => 'file',
      'source_configuration' => ['source_field' => 'field_media_document'],
    ])->save();

    require_once \Drupal::root() . '/' . \Drupal::service('extension.list.theme')->getPath('mukurtu_v4') . '/mukurtu_v4.theme';
  }

  protected function createTestFile(string $filename): File {
    $directory = 'public://';
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $file = File::create(['uri' => 'public://' . $filename, 'filename' => $filename, 'status' => 1]);
    file_put_contents($file->getFileUri(), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
    $file->save();
    return $file;
  }

  public function testDocumentThumbnailHasAltText(): void {
    $file = $this->createTestFile('a11y-test-thumbnail.png');
    $media = Media::create(['bundle' => 'document', 'name' => 'Sample Field Notes']);
    $media->save();

    $variables = [
      'media' => $media,
      'content' => ['thumbnail' => [0 => ['#theme' => 'image_formatter', '#item' => $file]]],
    ];
    mukurtu_v4_preprocess_media__document__carousel_thumbnail($variables);

    $this->assertEquals('Sample Field Notes', $variables['content']['thumbnail'][0]['#attributes']['alt'] ?? NULL);
  }

  public function testDocumentThumbnailAltTextIsTranslated(): void {
    \Drupal\language\Entity\ConfigurableLanguage::createFromLangcode('es')->save();

    $file = $this->createTestFile('a11y-test-thumbnail-es.png');
    $media = Media::create(['bundle' => 'document', 'name' => 'Sample Field Notes']);
    $media->save();
    $spanish_media = $media->addTranslation('es', ['name' => 'Notas de Campo de Muestra'] + $media->toArray());
    $spanish_media->save();

    // Pass the Spanish translation object, but leave the ambient content
    // language at the site default (English). A raw $media->label() call
    // would return the Spanish label unconditionally (that's the language
    // of the object handed in), so asserting the ENGLISH label here proves
    // the preprocess function is resolving via getTranslationFromContext()
    // - i.e. respecting the negotiated context - rather than trusting
    // whatever translation object it happens to receive.
    $variables = [
      'media' => $spanish_media,
      'content' => ['thumbnail' => [0 => ['#theme' => 'image_formatter', '#item' => $file]]],
    ];
    mukurtu_v4_preprocess_media__document__carousel_thumbnail($variables);

    $this->assertEquals('Sample Field Notes', $variables['content']['thumbnail'][0]['#attributes']['alt'] ?? NULL);
  }

  public function testNoThumbnailDoesNotError(): void {
    $media = Media::create(['bundle' => 'document', 'name' => 'No Thumbnail']);
    $media->save();

    $variables = ['media' => $media, 'content' => []];
    mukurtu_v4_preprocess_media__document__carousel_thumbnail($variables);

    $this->assertArrayNotHasKey('thumbnail', $variables['content']);
  }

}
