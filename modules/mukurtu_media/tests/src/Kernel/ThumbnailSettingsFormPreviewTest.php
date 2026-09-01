<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Serialization\Yaml;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\MediaType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the default-thumbnail preview built by ThumbnailSettingsForm.
 *
 * '#preview_image_style' is a Field-API ImageWidget property that plain
 * '#type' => 'managed_file' elements silently ignore, so it was a no-op on
 * this form. The fix builds a sibling 'image_style' themed render element
 * instead, mirroring how core's ImageWidget::process() builds its own
 * preview key.
 *
 * @see \Drupal\mukurtu_media\Form\ThumbnailSettingsForm::buildForm()
 */
#[Group('mukurtu_media')]
class ThumbnailSettingsFormPreviewTest extends KernelTestBase {

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
   *
   * mukurtu_media.schema.yml declares mukurtu_thumbnail.settings' keys under
   * a nested 'default_thumbnail' sequence, but ThumbnailSettingsForm actually
   * reads/writes flat, per-bundle top-level keys (see getConfigKey()) -- a
   * pre-existing schema/implementation mismatch unrelated to the preview fix
   * under test here. Matches Settings1761UpdateHooksTest's use of the same
   * escape hatch for a similar reason.
   */
  protected $strictConfigSchema = FALSE;

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
    // layout_builder and comment that this test doesn't need). This
    // matches ThumbnailAltTextAutofillTest's approach for the same module.
    $mukurtuCorePath = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    foreach (['audio', 'video', 'document', 'image'] as $bundle) {
      $definition = Yaml::decode(file_get_contents("$mukurtuCorePath/config/install/media.type.$bundle.yml"));
      MediaType::create($definition)->save();
    }
  }

  /**
   * Creates a permanent image file entity for use as a default thumbnail.
   */
  protected function createImageFile(string $filename = 'thumbnail.png'): File {
    $file = File::create([
      'uri' => "public://$filename",
      'filename' => $filename,
      'filemime' => 'image/png',
      'status' => 1,
    ]);
    $file->save();
    return $file;
  }

  /**
   * Creates a non-image file entity, to test the mime-type guard.
   */
  protected function createNonImageFile(string $filename = 'document.pdf'): File {
    $file = File::create([
      'uri' => "public://$filename",
      'filename' => $filename,
      'filemime' => 'application/pdf',
      'status' => 1,
    ]);
    $file->save();
    return $file;
  }

  /**
   * Builds the form and returns its render array.
   */
  protected function buildThumbnailSettingsForm(): array {
    $form_object = \Drupal::classResolver(\Drupal\mukurtu_media\Form\ThumbnailSettingsForm::class);
    $form = [];
    $form_state = new FormState();
    return $form_object->buildForm($form, $form_state);
  }

  /**
   * A preview element is present, correctly themed, and points at the
   * configured file, when config already holds an image fid.
   */
  public function testPreviewPresentWhenImageFileConfigured(): void {
    $file = $this->createImageFile('audio-default.png');
    \Drupal::configFactory()->getEditable('mukurtu_thumbnail.settings')
      ->set('audio_default_thumbnail', [$file->id()])
      ->save();

    $form = $this->buildThumbnailSettingsForm();

    $this->assertArrayHasKey('audio_preview', $form['default_thumbnail']);
    $preview = $form['default_thumbnail']['audio_preview'];
    $this->assertSame('image_style', $preview['#theme']);
    $this->assertSame('thumbnail', $preview['#style_name']);
    $this->assertSame($file->getFileUri(), $preview['#uri']);

    // The preview must sit above the managed_file element so it visually
    // renders as a preview of the current value.
    $this->assertLessThan(
      $form['default_thumbnail']['audio']['#weight'] ?? 0,
      $preview['#weight']
    );
  }

  /**
   * No preview element is built for a bundle with no configured file.
   */
  public function testNoPreviewWhenNoFileConfigured(): void {
    $form = $this->buildThumbnailSettingsForm();

    $this->assertArrayNotHasKey('video_preview', $form['default_thumbnail']);
    // The managed_file element itself is still present.
    $this->assertArrayHasKey('video', $form['default_thumbnail']);
    $this->assertSame('managed_file', $form['default_thumbnail']['video']['#type']);
  }

  /**
   * No preview is built when the configured fid points at a non-image file
   * (the mime-type guard added alongside the preview fix).
   */
  public function testNoPreviewForNonImageFile(): void {
    $file = $this->createNonImageFile();
    \Drupal::configFactory()->getEditable('mukurtu_thumbnail.settings')
      ->set('document', [$file->id()])
      ->save();

    $form = $this->buildThumbnailSettingsForm();

    $this->assertArrayNotHasKey('document_preview', $form['default_thumbnail']);
  }

  /**
   * No preview is built, and nothing errors, when the configured fid does
   * not resolve to an existing file entity (stale config).
   */
  public function testNoPreviewForMissingFile(): void {
    \Drupal::configFactory()->getEditable('mukurtu_thumbnail.settings')
      ->set('video_default_thumbnail', [99999])
      ->save();

    $form = $this->buildThumbnailSettingsForm();

    $this->assertArrayNotHasKey('video_preview', $form['default_thumbnail']);
  }

  /**
   * The audio/video config keys are suffixed (getConfigKey()); the preview
   * logic must read/key off the same suffixed key as the managed_file
   * element, not the bare bundle name.
   */
  public function testSuffixedConfigKeyUsedForVideoBundle(): void {
    $file = $this->createImageFile('video-default.png');
    \Drupal::configFactory()->getEditable('mukurtu_thumbnail.settings')
      ->set('video_default_thumbnail', [$file->id()])
      ->save();

    $form = $this->buildThumbnailSettingsForm();

    $this->assertArrayHasKey('video_preview', $form['default_thumbnail']);
    $this->assertSame($file->getFileUri(), $form['default_thumbnail']['video_preview']['#uri']);
    $this->assertSame([$file->id()], $form['default_thumbnail']['video']['#default_value']);
  }

}
