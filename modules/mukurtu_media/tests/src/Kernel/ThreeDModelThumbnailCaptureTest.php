<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Serialization\Yaml;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\mukurtu_media\Entity\ThreeDModel;
use Drupal\mukurtu_media\ThreeDModelThumbnailCapture;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests capturing a 3D model's thumbnail from the viewer (#2809).
 *
 * The browser half is in 3d-model-viewer.offline.spec.ts, in tests/playwright.
 *
 * @see \Drupal\mukurtu_media\ThreeDModelThumbnailCapture
 * @see mukurtu_media_3d_model_form_alter()
 */
#[Group('mukurtu_media')]
class ThreeDModelThumbnailCaptureTest extends ProtocolAwareEntityTestBase {

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
  protected function setUpFilesystem(): void {
    parent::setUpFilesystem();
    // 3D models and their thumbnails are private files.
    $this->setSetting('file_private_path', $this->siteDirectory . '/private');
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);

    $path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    MediaType::create(Yaml::decode(file_get_contents("$path/config/install/media.type.3d_model.yml")))->save();
  }

  /**
   * Returns a PNG of the given size.
   */
  protected function png(int $width = 4, int $height = 3): string {
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);
    return ob_get_clean();
  }

  /**
   * Returns a capture as the browser sends it.
   */
  protected function dataUrl(string $data, string $type = 'image/png'): string {
    return "data:$type;base64," . base64_encode($data);
  }

  /**
   * Creates a 3D model, with a thumbnail if one is given.
   */
  protected function createModel(?File $thumbnail = NULL): ThreeDModel {
    $values = ['bundle' => '3d_model', 'name' => 'Woven basket'];
    if ($thumbnail) {
      $values['field_thumbnail'] = ['target_id' => $thumbnail->id(), 'alt' => 'An uploaded thumbnail'];
    }
    $media = Media::create($values);
    $media->save();
    return $media;
  }

  /**
   * A PNG data URL within the limits is accepted, and anything else isn't.
   */
  public function testDecode(): void {
    $png = $this->png();
    $this->assertSame($png, ThreeDModelThumbnailCapture::decode($this->dataUrl($png)));

    $jpeg_image = imagecreatetruecolor(4, 3);
    ob_start();
    imagejpeg($jpeg_image);
    $jpeg = ob_get_clean();

    $rejected = [
      'empty' => '',
      'not a data URL' => base64_encode($png),
      'a JPEG said to be a PNG' => $this->dataUrl($jpeg),
      'a PNG said to be a JPEG' => $this->dataUrl($png, 'image/jpeg'),
      'not an image' => $this->dataUrl('<?php echo "hello";'),
      'not base64' => 'data:image/png;base64,***',
      'something after the data' => $this->dataUrl($png) . '"><script>',
      'too wide' => $this->dataUrl($this->png(ThreeDModelThumbnailCapture::MAX_DIMENSION + 1, 1)),
      'too tall' => $this->dataUrl($this->png(1, ThreeDModelThumbnailCapture::MAX_DIMENSION + 1)),
      'too big' => 'data:image/png;base64,' . str_repeat('A', (int) ceil(ThreeDModelThumbnailCapture::MAX_BYTES / 3) * 4 + 4),
    ];
    foreach ($rejected as $case => $value) {
      $this->assertNull(ThreeDModelThumbnailCapture::decode($value), $case);
    }
  }

  /**
   * A capture is saved as a private file and replaces the thumbnail.
   */
  public function testSaveReplacesThumbnail(): void {
    $directory = 'private://uploads';
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $uploaded = File::create(['uri' => 'private://uploads/basket.png', 'filename' => 'basket.png', 'status' => 1]);
    file_put_contents($uploaded->getFileUri(), $this->png());
    $uploaded->save();
    $media = $this->createModel($uploaded);

    $png = $this->png(8, 6);
    $file = ThreeDModelThumbnailCapture::save($png, $media);
    $media->save();

    $this->assertNotNull($file);
    $this->assertTrue($file->isPermanent());
    $this->assertSame('private://' . date('Y-m') . '/3d-model-thumbnail.png', $file->getFileUri());
    $this->assertSame($png, file_get_contents($file->getFileUri()));

    $media = Media::load($media->id());
    $this->assertSame((string) $file->id(), (string) $media->get('field_thumbnail')->target_id);
    $this->assertSame((string) $file->id(), (string) $media->get('thumbnail')->target_id);
    // Filled from the name, as for an uploaded thumbnail.
    $this->assertSame('Woven basket', $media->get('field_thumbnail')->alt);
  }

  /**
   * The add and edit forms get the hidden field and the handlers, in order.
   */
  public function testFormAlter(): void {
    $form = [
      'field_media_3d_model' => ['widget' => [0 => ['#process' => []]]],
      'field_thumbnail' => ['widget' => [0 => ['#process' => []]]],
      'actions' => ['submit' => ['#submit' => ['::submitForm', '::save']]],
    ];
    mukurtu_media_3d_model_form_alter($form);

    $this->assertSame('hidden', $form['thumbnail_capture']['#type']);
    $this->assertContains('mukurtu_media_3d_model_capture_process', $form['field_media_3d_model']['widget'][0]['#process']);
    $this->assertContains('mukurtu_media_3d_model_capture_validate', $form['#validate']);
    // After the entity is built from the form, and before it's saved.
    $this->assertSame(['::submitForm', 'mukurtu_media_3d_model_capture_submit', '::save'], $form['actions']['submit']['#submit']);
  }

  /**
   * The form handlers save a valid capture and reject an invalid one.
   */
  public function testFormHandlers(): void {
    $media = $this->createModel();
    $form_object = \Drupal::entityTypeManager()->getFormObject('media', 'edit')->setEntity($media);
    $form = [];

    // An invalid capture is an error, and nothing is saved.
    $form_state = (new FormState())->setFormObject($form_object)->setValue('thumbnail_capture', $this->dataUrl('not an image'));
    mukurtu_media_3d_model_capture_validate($form, $form_state);
    $this->assertArrayHasKey('thumbnail_capture', $form_state->getErrors());
    $this->assertNull($form_state->get('mukurtu_3d_model_capture'));

    // No capture leaves the thumbnail alone.
    $form_state = (new FormState())->setFormObject($form_object)->setValue('thumbnail_capture', '');
    mukurtu_media_3d_model_capture_validate($form, $form_state);
    mukurtu_media_3d_model_capture_submit($form, $form_state);
    $this->assertSame([], $form_state->getErrors());
    $this->assertTrue($media->get('field_thumbnail')->isEmpty());

    // A valid capture becomes the thumbnail.
    $form_state = (new FormState())->setFormObject($form_object)->setValue('thumbnail_capture', $this->dataUrl($this->png()));
    mukurtu_media_3d_model_capture_validate($form, $form_state);
    mukurtu_media_3d_model_capture_submit($form, $form_state);
    $this->assertSame([], $form_state->getErrors());
    $this->assertFalse($media->get('field_thumbnail')->isEmpty());
    $this->assertStringStartsWith('private://', $media->get('field_thumbnail')->entity->getFileUri());
  }

}
