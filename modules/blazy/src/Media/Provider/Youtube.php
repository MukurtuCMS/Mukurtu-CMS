<?php

namespace Drupal\blazy\Media\Provider;

use Drupal\Component\Utility\UrlHelper;

/**
 * Provides Youtube utility.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class Youtube {

  /**
   * Returns the expected input URL, specific for Youtube.
   *
   * OEmbed Resource doesn't accept `/embed`, provides a conversion helper,
   * normally seen at BlazyFilter with youtube embed copy/paste, without
   * creating media entities. Or when given an embed code by VEF, etc.
   *
   * @param string $input
   *   The given url.
   * @param bool $privacy
   *   Whether to prioritize privacy, or default.
   *
   * @return string
   *   The input url.
   */
  public static function fromEmbed($input, $privacy = FALSE): ?string {
    if ($input) {
      if (strpos($input, 'youtube.com') !== FALSE || strpos($input, 'youtu.be') !== FALSE) {
        if (strpos($input, 'youtube.com/embed') !== FALSE) {
          $search = '/youtube\.com\/embed\/([a-zA-Z0-9]+)/smi';
          $replace = "youtube.com/watch?v=$1";
          $input = preg_replace($search, $replace, $input);
        }
        if ($privacy) {
          $input = self::toNoCookieEmbed($input);
        }
      }
    }

    return $input;
  }

  /**
   * Returns the www.youtube-nocookie.com URL for privacy-enhanced YouTube.
   *
   * YouTube videos will load via www.youtube-nocookie.com. This prevents
   * YouTube from storing tracking cookies until the user plays the video,
   * aiding GDPR compliance.
   *
   * @param string $url
   *   The given url.
   *
   * @return string
   *   The input url.
   */
  public static function toNoCookieEmbed(string $url): ?string {
    $parts = parse_url(trim($url));

    if (empty($parts['host'])) {
      return NULL;
    }

    $host = strtolower($parts['host']);
    parse_str($parts['query'] ?? '', $query);

    $video_id = NULL;

    // youtu.be short links.
    if ($host === 'youtu.be') {
      $video_id = ltrim($parts['path'] ?? '', '/');
    }

    // youtube.com variants.
    elseif (str_contains($host, 'youtube.com')) {
      // watch?v=VIDEO_ID.
      if (!empty($query['v'])) {
        $video_id = $query['v'];
      }
      // /embed/ID or /shorts/ID.
      elseif (!empty($parts['path'])) {
        if (preg_match('~^/(embed|shorts)/([^/?]+)~', $parts['path'], $m)) {
          $video_id = $m[2];
        }
      }
    }

    if (!$video_id) {
      return NULL;
    }

    // Build embed parameters.
    $params = [];

    // Start time.
    if (!empty($query['t'])) {
      $params['start'] = is_numeric($query['t'])
        ? (int) $query['t']
        : preg_replace('/\D/', '', $query['t']);
    }
    elseif (!empty($query['start'])) {
      $params['start'] = (int) $query['start'];
    }

    // Playlist.
    if (!empty($query['list'])) {
      $params['list'] = $query['list'];
    }

    $embed_url = 'https://www.youtube-nocookie.com/embed/' . $video_id;

    if ($params) {
      $embed_url .= '?' . UrlHelper::buildQuery($params);
    }

    return $embed_url;
  }

}
