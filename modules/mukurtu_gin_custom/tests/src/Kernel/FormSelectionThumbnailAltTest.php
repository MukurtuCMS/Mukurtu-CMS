<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_gin_custom\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_gin_custom_preprocess_field() on selection-card thumbnails.
 *
 * Media renders in the form_selection view mode only inside a node's
 * selection card, whose heading names the item. Generated thumbnails
 * (documents, audio, video) carry no alt, so the <img> had no alt attribute
 * at all (WCAG 1.1.1). They should render as decorative, while author-written
 * alt text is kept.
 */
#[Group('mukurtu_gin_custom')]
class FormSelectionThumbnailAltTest extends KernelTestBase {

  use MediaTypeCreationTrait;
  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'image',
    'media',
    'mukurtu_gin_custom',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['field', 'system', 'image', 'file', 'media']);

    EntityViewMode::create([
      'id' => 'media.form_selection',
      'targetEntityType' => 'media',
      'label' => 'Form selection',
    ])->save();
  }

  /**
   * Creates a media type whose form_selection display shows the thumbnail.
   */
  protected function createTypeWithThumbnailDisplay(string $source): string {
    $type = $this->createMediaType($source);
    foreach (['default', 'form_selection'] as $mode) {
      EntityViewDisplay::create([
        'targetEntityType' => 'media',
        'bundle' => $type->id(),
        'mode' => $mode,
        'status' => TRUE,
      ])->setComponent('thumbnail', [
        'type' => 'image',
        'label' => 'hidden',
        'settings' => ['image_style' => '', 'image_link' => ''],
      ])->save();
    }
    return $type->id();
  }

  /**
   * Renders a media entity and returns its thumbnail <img> tag.
   */
  protected function renderImg(MediaInterface $media, string $view_mode): string {
    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('media')
      ->view($media, $view_mode);
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    $this->assertSame(1, preg_match('/<img[^>]*>/', $html, $matches), 'The thumbnail renders.');
    return $matches[0];
  }

  /**
   * A generated thumbnail with no alt renders as decorative, only here.
   */
  public function testGeneratedThumbnailIsDecorative(): void {
    $bundle = $this->createTypeWithThumbnailDisplay('file');
    $file = File::create(['uri' => $this->getTestFiles('text')[0]->uri]);
    $file->save();
    $media = Media::create([
      'bundle' => $bundle,
      'name' => 'A document',
      'field_media_file' => $file->id(),
    ]);
    $media->save();
    // Current core stores an empty alt on new generated thumbnails, but
    // existing sites have media whose thumbnail alt is NULL, which renders
    // with no alt attribute at all. Reproduce that. A re-save does not
    // regenerate the thumbnail, since the source field is unchanged.
    $media->get('thumbnail')->alt = NULL;
    $media->save();
    $this->assertNull($media->get('thumbnail')->alt);

    $this->assertStringContainsString('alt=""', $this->renderImg($media, 'form_selection'));
    // Negative control: other view modes are untouched.
    $this->assertStringNotContainsString('alt=', $this->renderImg($media, 'default'));
  }

  /**
   * Author-written alt text is not replaced.
   */
  public function testAuthoredAltIsKept(): void {
    $bundle = $this->createTypeWithThumbnailDisplay('image');
    $file = File::create(['uri' => $this->getTestFiles('image')[0]->uri]);
    $file->save();
    $media = Media::create([
      'bundle' => $bundle,
      'name' => 'A photo',
      'field_media_image' => ['target_id' => $file->id(), 'alt' => 'A river at dusk'],
    ]);
    $media->save();

    $this->assertStringContainsString('alt="A river at dusk"', $this->renderImg($media, 'form_selection'));
  }

}
