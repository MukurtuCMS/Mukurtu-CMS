<?php

namespace Drupal\mukurtu_media;

/**
 * Shared mapping of file-based media bundles to their source field and
 * accepted extensions, used wherever a raw uploaded file needs to become a
 * Media entity without going through the Media Library's own add form
 * (bulk upload, public submission uploads, etc.).
 */
final class MediaTypeExtensions {

  /**
   * File-based media bundles, keyed by bundle ID.
   */
  public const SUPPORTED_TYPES = [
    'image' => [
      'field' => 'field_media_image',
      'extensions' => 'png gif jpg jpeg webp',
      'uri_scheme' => 'private',
      'alt' => TRUE,
    ],
    'audio' => [
      'field' => 'field_media_audio_file',
      'extensions' => 'mp3 wav aac m4a ogg',
      'uri_scheme' => 'private',
    ],
    'document' => [
      'field' => 'field_media_document',
      'extensions' => 'txt rtf doc docx ppt pptx xls xlsx pdf odf odg odp ods odt fodt fods fodp fodg key numbers pages csv sxw zip rar gz 7z tar',
      'uri_scheme' => 'private',
    ],
    'video' => [
      'field' => 'field_media_video_file',
      'extensions' => 'mp4 webm ogv',
      'uri_scheme' => 'private',
    ],
  ];

  /**
   * Finds which supported bundle a filename's extension belongs to.
   *
   * @param string $filename
   *   The file name, e.g. "photo.jpg".
   *
   * @return string|null
   *   The bundle ID (e.g. "image"), or NULL if the extension doesn't match
   *   any supported bundle.
   */
  public static function bundleForFilename(string $filename): ?string {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($extension === '') {
      return NULL;
    }
    foreach (self::SUPPORTED_TYPES as $bundle => $config) {
      if (in_array($extension, explode(' ', $config['extensions']), TRUE)) {
        return $bundle;
      }
    }
    return NULL;
  }

}
