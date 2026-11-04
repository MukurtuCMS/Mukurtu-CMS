<?php

declare(strict_types=1);

namespace Drupal\mukurtu_media\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\media\IFrameMarkup;

/**
 * Hook implementations for the oEmbed iframe page.
 */
final class OEmbedIframeHooks {

  /**
   * Implements hook_preprocess_HOOK() for media-oembed-iframe.html.twig.
   *
   * MukurtuResourceFetcher puts a Vimeo segment's start time on the player
   * src as a #t= fragment. When Blazy opens a video in a lightbox, its own
   * preprocess appends autoplay=1 to the end of the src, which lands inside
   * that fragment, so the player never sees it. This moves anything after
   * the #t= value back into the query string, ahead of the fragment.
   *
   * @see \Drupal\mukurtu_media\OEmbed\MukurtuResourceFetcher::applyVimeoSegment()
   * @see \Drupal\blazy\Hook\ThemeHooks::preprocessMediaOembedIframe()
   */
  #[Hook('preprocess_media_oembed_iframe', order: new OrderAfter(['blazy']))]
  public function preprocessMediaOembedIframe(array &$variables): void {
    if (empty($variables['media'])) {
      return;
    }
    $media = (string) $variables['media'];
    $fixed = preg_replace_callback(
      '/src="([^"#]+)#(t=\d+s)(&amp;|&)([^"]+)"/',
      function (array $matches): string {
        [, $base, $time, $separator, $params] = $matches;
        $query_separator = str_contains($base, '?') ? $separator : '?';
        return 'src="' . $base . $query_separator . $params . '#' . $time . '"';
      },
      $media
    );
    if ($fixed !== NULL && $fixed !== $media) {
      $variables['media'] = IFrameMarkup::create($fixed);
    }
  }

}
