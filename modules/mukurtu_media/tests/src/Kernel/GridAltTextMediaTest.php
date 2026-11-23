<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\mukurtu_import\BulkEdit\GridColumns;
use Drupal\mukurtu_import\BulkEdit\GridRowSaver;
use Drupal\mukurtu_media\Plugin\Validation\Constraint\ImageAltRequiredConstraint;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the per-item grid's alternative text columns on Mukurtu media types.
 *
 * Uses Mukurtu's own Image and Document bundle classes, which define the
 * image fields and require Image's alternative text on import.
 *
 * @see \Drupal\Tests\mukurtu_import\Kernel\GridAltTextTest
 */
#[Group('mukurtu_media')]
class GridAltTextMediaTest extends MukurtuMediaTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate',
    'mukurtu_import',
    'mukurtu_export',
    'mukurtu_multipage_items',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('user', ['users_data']);
    // Mukurtu Export needs Multipage Items, whose hooks run on each save.
    $this->installEntitySchema('multipage_item');
    $this->installEntitySchema('path_alias');
  }

  /**
   * Creates a saved 1x1 PNG file.
   */
  protected function createImageFile(string $name): FileInterface {
    file_put_contents("public://$name", base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    $file = File::create([
      'uri' => "public://$name",
      'filename' => $name,
      'filemime' => 'image/png',
      'uid' => $this->currentUser->id(),
      'status' => 1,
    ]);
    $file->save();
    return $file;
  }

  /**
   * Reloads a media item.
   */
  protected function reload(MediaInterface $media): MediaInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('media');
    $storage->resetCache([$media->id()]);
    return $storage->load($media->id());
  }

  /**
   * Clearing an Image's required alternative text is refused on its cell.
   */
  public function testRequiredAltRefused(): void {
    $file = $this->createImageFile('basket.png');
    $media = $this->buildMedia('image', 'Basket');
    $media->set('field_media_image', ['target_id' => $file->id(), 'alt' => 'A woven basket']);
    $media->save();
    $revision = $media->getRevisionId();

    $errors = (new GridRowSaver())->save($this->reload($media), ['field_media_image/alt' => ''], 'Grid log');

    $this->assertSame(['field_media_image/alt'], array_keys($errors));
    $this->assertStringContainsString((string) (new ImageAltRequiredConstraint())->message, implode(' ', $errors['field_media_image/alt']));
    $saved = $this->reload($media);
    $this->assertSame('A woven basket', $saved->get('field_media_image')->alt);
    $this->assertSame($revision, $saved->getRevisionId());
  }

  /**
   * A Document's Thumbnail alternative text can be edited, keeping the image.
   */
  public function testThumbnailAlt(): void {
    $available = (new GridColumns(_mukurtu_core_bulk_edit_columns('mukurtu_bulk_edit_media')))->available('document');
    $this->assertSame('Thumbnail alternative text', (string) ($available['field_thumbnail/alt'] ?? ''));

    $file = $this->createImageFile('report-cover.png');
    $media = $this->buildMedia('document', 'Report');
    $media->set('field_thumbnail', ['target_id' => $file->id(), 'alt' => 'Old text']);
    $media->save();

    $errors = (new GridRowSaver())->save($this->reload($media), ['field_thumbnail/alt' => 'Report cover'], 'Grid log');

    $this->assertSame([], $errors);
    $thumbnail = $this->reload($media)->get('field_thumbnail');
    $this->assertSame((string) $file->id(), (string) $thumbnail->target_id);
    $this->assertSame('Report cover', $thumbnail->alt);
  }

}
