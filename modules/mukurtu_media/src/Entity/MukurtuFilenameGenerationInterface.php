<?php

namespace Drupal\mukurtu_media\Entity;

/**
 * Provides an interface defining filename generation methods on media file
 * upload.
 */
interface MukurtuFilenameGenerationInterface {
  /**
   * Retrieves the filename of the media file.
   *
   * @return string|null
   *   The name of the media file if set, NULL otherwise.
   */
  public function getMediaFilename();

  /**
   * Checks if the user has uploaded a media file.
   *
   * @return bool
   *   True if the media item contains a media file, false otherwise.
   */
  public function hasUploadedMediaFile();
}
