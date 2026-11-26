<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_media\Form\BulkMediaUploadForm;
use Drupal\mukurtu_media\Form\BulkMediaUrlCreateForm;
use Drupal\mukurtu_media\MediaTypeExtensions;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the bulk upload and bulk add page titles can be translated.
 *
 * The titles used to be built by joining English words, so translators never
 * saw them. Each is now a whole phrase, the same as its link on the Add media
 * page.
 */
#[Group('mukurtu_media')]
class BulkMediaPageTitleTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Each bulk upload type has its own translatable title.
   */
  public function testBulkUploadTitles(): void {
    $expected = [
      'image' => 'Bulk upload images',
      'audio' => 'Bulk upload audio',
      'document' => 'Bulk upload documents',
      'video' => 'Bulk upload videos',
      '3d_model' => 'Bulk upload 3D models',
    ];
    $this->assertSame(array_keys($expected), array_keys(MediaTypeExtensions::SUPPORTED_TYPES), 'Give each supported type its own title.');
    foreach ($expected as $media_type => $title) {
      $this->assertPhrase($title, BulkMediaUploadForm::getTitle($media_type));
    }
    $this->assertPhrase('Bulk upload media', BulkMediaUploadForm::getTitle('not_a_type'));
  }

  /**
   * Each bulk add type has its own translatable title.
   */
  public function testBulkAddTitles(): void {
    $expected = [
      'remote_video' => 'Bulk add remote videos',
      'soundcloud' => 'Bulk add SoundCloud items',
    ];
    $supported = (new \ReflectionClassConstant(BulkMediaUrlCreateForm::class, 'SUPPORTED_TYPES'))->getValue();
    $this->assertSame(array_keys($expected), array_keys($supported), 'Give each supported type its own title.');
    foreach ($expected as $media_type => $title) {
      $this->assertPhrase($title, BulkMediaUrlCreateForm::getTitle($media_type));
    }
    $this->assertPhrase('Bulk add media', BulkMediaUrlCreateForm::getTitle('not_a_type'));
  }

  /**
   * Asserts a title is a translatable string with no placeholders.
   */
  private function assertPhrase(string $expected, TranslatableMarkup $title): void {
    $this->assertSame($expected, $title->getUntranslatedString());
    $this->assertSame([], $title->getArguments());
  }

}
