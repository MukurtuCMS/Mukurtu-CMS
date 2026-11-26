<?php

declare(strict_types=1);

namespace Drupal\mukurtu_media;

/**
 * What the 3D model viewer (model-viewer) needs, wherever it's shown.
 *
 * The viewer's markup, JavaScript and vendored library are in mukurtu_v4.
 * This class holds the parts both mukurtu_v4 and the 3D Model add and edit
 * forms, which render in the admin theme, need.
 *
 * @see themes/mukurtu_v4/templates/media/_model-3d-viewer.html.twig
 */
final class ModelViewer {

  /**
   * Returns the translated text for the viewer's screen reader announcements.
   *
   * Its keys name the camera position: front, right, back and left,
   * prefixed with "upper-" or "lower-" when looking down on or up at the
   * model.
   *
   * @return string
   *   JSON, for the viewer's a11y attribute.
   */
  public static function a11y(): string {
    return json_encode([
      'front' => (string) t('View from the front'),
      'right' => (string) t('View from the right'),
      'back' => (string) t('View from the back'),
      'left' => (string) t('View from the left'),
      'upper-front' => (string) t('View from above the front'),
      'upper-right' => (string) t('View from above the right'),
      'upper-back' => (string) t('View from above the back'),
      'upper-left' => (string) t('View from above the left'),
      'lower-front' => (string) t('View from below the front'),
      'lower-right' => (string) t('View from below the right'),
      'lower-back' => (string) t('View from below the back'),
      'lower-left' => (string) t('View from below the left'),
      'interaction-prompt' => (string) t('Use a mouse, touch, the arrow keys, or the buttons below to move the model.'),
    ], JSON_UNESCAPED_UNICODE);
  }

  /**
   * Attaches the viewer, its controls and its decoder locations.
   *
   * Without the decoder locations, model-viewer downloads its decoders from
   * gstatic.com, a third-party request.
   *
   * @param array|null $attached
   *   A render array's #attached, which may not be set yet.
   */
  public static function attach(?array &$attached): void {
    $attached ??= [];
    $base = \Drupal::service('file_url_generator')->generateString(\Drupal::service('extension.list.theme')->getPath('mukurtu_v4') . '/libraries/model-viewer');
    $attached['library'][] = 'mukurtu_v4/media-3d-model';
    $attached['drupalSettings']['mukurtuModelViewer'] = [
      'dracoDecoderLocation' => $base . '/draco/',
      'ktx2TranscoderLocation' => $base . '/basis/',
    ];
  }

}
