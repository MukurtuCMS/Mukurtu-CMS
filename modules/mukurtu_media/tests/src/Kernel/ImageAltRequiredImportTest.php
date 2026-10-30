<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\migrate\MigrateException;
use Drupal\mukurtu_import\Plugin\migrate\destination\ProtocolAwareEntityContent;
use Drupal\mukurtu_media\Plugin\Validation\Constraint\ImageAltRequiredConstraint;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that importing image media requires alt text.
 *
 * The import destination attaches the ImageAltRequired constraint to
 * field_media_image before validating each image media row.
 *
 * @see \Drupal\mukurtu_import\Plugin\migrate\destination\ProtocolAwareEntityContent::addImageAltConstraint()
 */
#[Group('mukurtu_media')]
class ImageAltRequiredImportTest extends MukurtuMediaTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate',
    'mukurtu_import',
  ];

  /**
   * The import destination for media.
   */
  protected ProtocolAwareEntityContent $destination;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $migration = $this->container->get('plugin.manager.migration')->createStubMigration([
      'source' => ['plugin' => 'embedded_data', 'data_rows' => [], 'ids' => ['mid' => ['type' => 'integer']]],
      'process' => [],
      'destination' => ['plugin' => 'entity:media'],
    ]);
    $destination = $migration->getDestinationPlugin();
    $this->assertInstanceOf(ProtocolAwareEntityContent::class, $destination);
    $this->destination = $destination;
  }

  /**
   * Builds an unsaved image media item with an uploaded file.
   */
  protected function buildImageMedia(string $alt): Media {
    $file = File::create([
      'uri' => 'public://alt-test.jpg',
      'filename' => 'alt-test.jpg',
      'filemime' => 'image/jpeg',
      'uid' => $this->currentUser->id(),
      'status' => 1,
    ]);
    $file->save();

    $media = $this->buildMedia('image', 'Alt Test');
    $media->set('field_media_image', ['target_id' => $file->id(), 'alt' => $alt]);
    return $media;
  }

  /**
   * An image without alt text fails validation with a clear message.
   */
  public function testMissingAltTextFailsValidation(): void {
    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage((string) (new ImageAltRequiredConstraint())->message);
    $this->destination->validateEntity($this->buildImageMedia(''));
  }

  /**
   * An image with alt text passes validation.
   */
  public function testAltTextPassesValidation(): void {
    $this->destination->validateEntity($this->buildImageMedia('A descriptive alt text'));
    $this->addToAssertionCount(1);
  }

}
