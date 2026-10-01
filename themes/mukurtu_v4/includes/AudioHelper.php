<?php

/**
 * @file
 * Static helper for audio media theme processing.
 */

/**
 * Resolves thumbnail URLs and other shared audio preprocess logic.
 */
class MukurtuV4AudioHelper {

  /**
   * Returns the best available thumbnail URL for an audio media entity.
   *
   * Priority order:
   *   1. User-uploaded field_thumbnail on the media entity.
   *   2. Admin-configured site default (mukurtu_thumbnail.settings).
   *   3. Static audio.png bundled with the theme.
   *
   * field_thumbnail is hidden in the media_assets view mode, so the URL must
   * be resolved here rather than from the template's content array.
   *
   * A tier whose file exists but whose stream wrapper is unavailable falls
   * through to the next one, so a site with an unregistered scheme shows the
   * bundled icon instead of fataling on every audio thumbnail.
   *
   * @param \Drupal\media\Entity\Media $media
   *   The audio media entity.
   * @param array $variables
   *   Preprocess variables array; cache tags for the config are appended.
   *
   * @return string
   *   Root-relative or absolute URL for the thumbnail image.
   */
  public static function fallbackThumbnailUrl($media, array &$variables): string {
    if (!$media->get('field_thumbnail')->isEmpty()) {
      $url = MukurtuV4FileUrl::fromFile($media->get('field_thumbnail')->entity);
      if ($url !== NULL) {
        return $url;
      }
    }

    // Bust the render cache whenever the admin thumbnail config changes.
    $variables['#cache']['tags'][] = 'config:mukurtu_thumbnail.settings';

    $config = \Drupal::config('mukurtu_thumbnail.settings');
    $defaultFid = $config->get('audio')[0] ?? $config->get('audio_default_thumbnail')[0] ?? NULL;
    if ($defaultFid) {
      $url = MukurtuV4FileUrl::fromFile(\Drupal\file\Entity\File::load($defaultFid));
      if ($url !== NULL) {
        return $url;
      }
    }

    return '/profiles/mukurtu/themes/mukurtu_v4/images/audio.png';
  }

}
