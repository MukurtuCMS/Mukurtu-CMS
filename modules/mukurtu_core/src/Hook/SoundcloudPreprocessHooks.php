<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for gating SoundCloud embeds behind Klaro consent.
 *
 * Klaro's own hook_preprocess_field() (klaro_preprocess_field() in
 * klaro.module) only recognizes the "iframe" field type and the oembed,
 * html, video_embed_field_video, and simple_gmap formatters. It never sees
 * SoundCloud media because media_entity_soundcloud's SoundcloudEmbedFormatter
 * renders through its own '#theme' => 'media_soundcloud_embed' render array,
 * bypassing all of those code paths. This mirrors klaro_preprocess_field()'s
 * matching logic for that theme hook so SoundCloud embeds get gated by
 * cookie consent the same way YouTube/Vimeo embeds already are.
 */
final class SoundcloudPreprocessHooks {

  /**
   * Implements hook_preprocess_HOOK() for media-soundcloud-embed.html.twig.
   *
   * Runs after media_entity_soundcloud's own
   * template_preprocess_media_soundcloud_embed(), which Drupal always
   * invokes before any module's hook_preprocess_HOOK() implementations for
   * the same theme hook, so $variables['url'] is reliably set here. The
   * empty() guard below is defensive insurance only, not a workaround for a
   * real ordering hazard.
   */
  #[Hook('preprocess_media_soundcloud_embed')]
  public function preprocessMediaSoundcloudEmbed(array &$variables): void {
    if (empty($variables['url']) || !\Drupal::hasService('klaro.helper')) {
      return;
    }

    /** @var \Drupal\klaro\Utility\KlaroHelper $helper */
    $helper = \Drupal::service('klaro.helper');

    // Mirror klaro_preprocess_field()'s early exit exactly.
    if (!$helper->hasAccess()
      || $helper->onDisabledUri()
      || !$helper->getSettings()->get('auto_decorate_preprocess_field')
      || !$helper->consentManagementRequired()) {
      return;
    }

    $app = $helper->matchKlaroApp($variables['url']);
    if ($app) {
      // Don't destructively rewrite $variables['url'] here; the template
      // still needs the real URL for the data-src attribute. Just signal
      // the blocked state and let media-soundcloud-embed.html.twig decide
      // what markup to render.
      $variables['klaro_app_id'] = $app->id();
    }
  }

}
