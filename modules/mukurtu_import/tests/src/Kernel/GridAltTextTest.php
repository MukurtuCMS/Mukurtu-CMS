<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\mukurtu_import\BulkEdit\GridColumns;
use Drupal\mukurtu_import\BulkEdit\GridRowSaver;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests editing an image's alternative text in the per-item grid.
 *
 * The importer replaces a whole image field, so changing only the text must
 * keep the image.
 */
#[Group('mukurtu_import')]
class GridAltTextTest extends MukurtuImportTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['media', 'image', 'mukurtu_export', 'mukurtu_multipage_items'];

  /**
   * An image media item.
   */
  protected MediaInterface $media;

  /**
   * The media type's image field.
   */
  protected string $sourceField;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('media');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['media', 'image', 'file']);
    // Mukurtu Export needs Multipage Items, whose hooks run on each save.
    $this->installEntitySchema('multipage_item');

    $media_type = $this->createMediaType('image', ['id' => 'photo']);
    $this->sourceField = $media_type->getSource()->getSourceFieldDefinition($media_type)->getName();

    // A 1x1 transparent PNG.
    file_put_contents('public://basket.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    $file = File::create([
      'uri' => 'public://basket.png',
      'filename' => 'basket.png',
      'uid' => $this->currentUser->id(),
      'status' => 1,
    ]);
    $file->save();

    $this->media = Media::create([
      'bundle' => 'photo',
      'name' => 'Basket',
      $this->sourceField => ['target_id' => $file->id(), 'alt' => 'Old text'],
      'uid' => $this->currentUser->id(),
    ]);
    $this->media->save();
  }

  /**
   * Reloads the media item.
   */
  protected function reload(): MediaInterface {
    $storage = $this->entityTypeManager->getStorage('media');
    $storage->resetCache([$this->media->id()]);
    return $storage->load($this->media->id());
  }

  /**
   * The grid offers the alternative text, not the image itself.
   */
  public function testColumns(): void {
    $available = (new GridColumns(_mukurtu_core_bulk_edit_columns('mukurtu_bulk_edit_media')))->available('photo');

    $this->assertArrayHasKey("{$this->sourceField}/alt", $available);
    $this->assertStringEndsWith(' alternative text', (string) $available["{$this->sourceField}/alt"]);
    $this->assertArrayNotHasKey($this->sourceField, $available);
    // Core's generated thumbnail isn't offered.
    $this->assertArrayNotHasKey('thumbnail/alt', $available);
  }

  /**
   * Changing the text keeps the image.
   */
  public function testAltChangeKeepsImage(): void {
    $fid = $this->media->get($this->sourceField)->target_id;

    $errors = (new GridRowSaver())->save($this->reload(), ["{$this->sourceField}/alt" => 'A woven cedar basket'], 'Grid log');

    $this->assertSame([], $errors);
    $image = $this->reload()->get($this->sourceField);
    $this->assertSame((string) $fid, (string) $image->target_id);
    $this->assertSame('A woven cedar basket', $image->alt);
  }

  /**
   * Alternative text with a semicolon stays whole.
   */
  public function testSemicolonKept(): void {
    $this->assertSame([], (new GridRowSaver())->save($this->reload(), ["{$this->sourceField}/alt" => 'Basket; woven by hand'], 'Grid log'));
    $this->assertSame('Basket; woven by hand', $this->reload()->get($this->sourceField)->alt);
  }

}
