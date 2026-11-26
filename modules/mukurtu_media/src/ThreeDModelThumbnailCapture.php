<?php

declare(strict_types=1);

namespace Drupal\mukurtu_media;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\mukurtu_media\Entity\ThreeDModel;

/**
 * A 3D model's thumbnail, captured from the viewer on its add or edit form.
 *
 * The browser sends the view as a PNG data URL. It's checked here before
 * being saved as the model's thumbnail.
 */
final class ThreeDModelThumbnailCapture {

  /**
   * The largest capture accepted, in bytes.
   */
  public const MAX_BYTES = 8 * 1024 * 1024;

  /**
   * The largest width or height accepted, in pixels.
   */
  public const MAX_DIMENSION = 4096;

  /**
   * Returns the PNG a capture holds, or NULL if it isn't a usable PNG.
   *
   * @param string $value
   *   The capture as the browser sent it: a data URL.
   */
  public static function decode(string $value): ?string {
    if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/]+={0,2})$#', $value, $matches)) {
      return NULL;
    }
    if (strlen($matches[1]) > (int) ceil(self::MAX_BYTES / 3) * 4) {
      return NULL;
    }
    $png = base64_decode($matches[1], TRUE);
    if ($png === FALSE || strlen($png) > self::MAX_BYTES) {
      return NULL;
    }
    $info = @getimagesizefromstring($png);
    if (!$info || $info[2] !== IMAGETYPE_PNG) {
      return NULL;
    }
    if ($info[0] < 1 || $info[1] < 1 || $info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION) {
      return NULL;
    }
    return $png;
  }

  /**
   * Saves a PNG as a model's thumbnail, replacing any it had.
   *
   * The thumbnail's alt text is left empty, so ThreeDModel::preSave() fills
   * it from the model's name, as it does for an uploaded thumbnail.
   *
   * @param string $png
   *   A PNG that decode() returned.
   * @param \Drupal\mukurtu_media\Entity\ThreeDModel $media
   *   The model. Not saved here.
   *
   * @return \Drupal\file\FileInterface|null
   *   The saved file, or NULL if it couldn't be written.
   */
  public static function save(string $png, ThreeDModel $media): ?FileInterface {
    $settings = $media->getFieldDefinition('field_thumbnail')->getSettings();
    $directory = $settings['uri_scheme'] . '://' . date('Y-m');
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    if (!$file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      return NULL;
    }
    try {
      $file = \Drupal::service('file.repository')->writeData($png, $directory . '/3d-model-thumbnail.png', FileExists::Rename);
    }
    catch (\Exception $e) {
      \Drupal::logger('mukurtu_media')->error('A captured 3D model thumbnail could not be saved: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
    $media->set('field_thumbnail', ['target_id' => $file->id(), 'alt' => '']);
    return $file;
  }

}
