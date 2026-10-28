<?php

namespace Drupal\mukurtu_media\OEmbed;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\media\OEmbed\ProviderRepositoryInterface;
use Drupal\media\OEmbed\Resource;
use Drupal\media\OEmbed\ResourceException;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;

/**
 * Decorates the core oEmbed resource fetcher with YouTube-specific handling.
 *
 * - Rewrites YouTube embeds to use youtube-nocookie.com (privacy-enhanced
 *   mode) and trims YouTube's UI chrome, on every view mode/formatter.
 * - If the primary oEmbed fetch fails (for example, outbound requests to
 *   YouTube are blocked on this host) and a YouTube Data API key is
 *   configured, falls back to the YouTube Data API to build an equivalent
 *   resource instead of leaving the embed broken.
 * - Carries a segment's start/end time from the video URL (for example
 *   ?t=90 or #t=1m30s) onto the embed, since providers drop it from the
 *   oEmbed HTML. YouTube honors start and end; Vimeo honors start only.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/565
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/557
 */
class MukurtuResourceFetcher implements ResourceFetcherInterface {

  /**
   * Constructs a MukurtuResourceFetcher object.
   */
  public function __construct(
    protected ResourceFetcherInterface $inner,
    protected ClientInterface $httpClient,
    protected KeyRepositoryInterface $keyRepository,
    protected ProviderRepositoryInterface $providers,
    protected LoggerInterface $logger,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function fetchResource($url) {
    $segment = $this->extractSegment($this->extractOriginalUrl($url) ?? '');

    try {
      $resource = $this->inner->fetchResource($url);
    }
    catch (ResourceException $e) {
      $video_id = $this->extractYoutubeVideoId($url);
      if ($video_id) {
        $fallback = $this->fetchFromYoutubeDataApi($video_id, $segment);
        if ($fallback) {
          return $fallback;
        }
      }
      throw $e;
    }

    if (!$resource->getHtml()) {
      return $resource;
    }

    $provider_name = $this->getProviderName($resource);
    if ($provider_name === 'YouTube') {
      return $this->rebuildResource($resource, $this->applyYoutubePrivacyParams($resource->getHtml(), $segment));
    }
    if ($provider_name === 'Vimeo' && $segment['start']) {
      return $this->rebuildResource($resource, $this->applyVimeoStartTime($resource->getHtml(), $segment['start']));
    }

    return $resource;
  }

  /**
   * Gets the name of a resource's oEmbed provider, if any.
   */
  protected function getProviderName(Resource $resource): ?string {
    $provider = $resource->getProvider();
    return $provider ? $provider->getName() : NULL;
  }

  /**
   * Rewrites a YouTube embed's iframe src to use privacy-enhanced mode.
   *
   * - youtube-nocookie.com: YouTube's privacy-enhanced embed domain, which
   *   does not set cookies until the viewer interacts with the player.
   * - rel=0: suppresses related videos at the end of playback.
   * - modestbranding=1: removes the YouTube logo from the control bar.
   * - start/end: the segment's start and end time in seconds, if any and if
   *   YouTube has not already set them.
   *
   * @param string $html
   *   The embed HTML returned by YouTube.
   * @param array{start: ?int, end: ?int} $segment
   *   The segment to play, as returned by extractSegment().
   */
  protected function applyYoutubePrivacyParams(string $html, array $segment = ['start' => NULL, 'end' => NULL]): string {
    return preg_replace_callback(
      '/src="([^"]+)"/',
      function (array $matches) use ($segment): string {
        $src = str_replace(
          ['//www.youtube.com/embed', '//youtube.com/embed'],
          '//www.youtube-nocookie.com/embed',
          $matches[1]
        );
        $separator = str_contains($src, '?') ? '&' : '?';
        return 'src="' . $src . $separator . 'rel=0&modestbranding=1' . $this->buildYoutubeSegmentParams($segment, $src) . '"';
      },
      $html
    );
  }

  /**
   * Builds the YouTube embed query parameters for a segment.
   *
   * YouTube's oEmbed HTML sometimes already includes a start time, so a
   * parameter is left out if the src already has it.
   *
   * @param array{start: ?int, end: ?int} $segment
   *   The segment to play, as returned by extractSegment().
   * @param string $src
   *   The iframe src the parameters will be added to.
   *
   * @return string
   *   The parameters, each prefixed with "&", or an empty string.
   */
  protected function buildYoutubeSegmentParams(array $segment, string $src = ''): string {
    $params = '';
    foreach (['start', 'end'] as $name) {
      if ($segment[$name] && !preg_match('/[?&](?:amp;)?' . $name . '=/', $src)) {
        $params .= '&' . $name . '=' . $segment[$name];
      }
    }
    return $params;
  }

  /**
   * Sets the start time on a Vimeo embed's iframe src.
   *
   * Vimeo's player takes the start time as a #t= fragment. It has no
   * equivalent for an end time.
   */
  protected function applyVimeoStartTime(string $html, int $start): string {
    return preg_replace_callback(
      '/src="([^"#]+)(?:#[^"]*)?"/',
      fn (array $matches): string => 'src="' . $matches[1] . '#t=' . $start . 's"',
      $html
    );
  }

  /**
   * Gets the segment's start and end time from a video URL.
   *
   * Reads the start and end query parameters used by YouTube embed URLs,
   * then the t query parameter or #t= fragment used by YouTube and Vimeo
   * share links. Values may be seconds ("90", "90s") or hours, minutes and
   * seconds ("1h2m3s"). Invalid values, and an end time that is not after
   * the start time, are ignored.
   *
   * @return array{start: ?int, end: ?int}
   *   The start and end time in seconds, each NULL if not set.
   */
  protected function extractSegment(string $video_url): array {
    $parts = parse_url($video_url);
    $query = [];
    $fragment = [];
    if (!empty($parts['query'])) {
      parse_str($parts['query'], $query);
    }
    if (!empty($parts['fragment'])) {
      parse_str($parts['fragment'], $fragment);
    }

    $start = $this->toSeconds($query['start'] ?? NULL)
      ?? $this->toSeconds($query['t'] ?? NULL)
      ?? $this->toSeconds($fragment['t'] ?? NULL);
    $end = $this->toSeconds($query['end'] ?? NULL);
    if ($end !== NULL && $end <= ($start ?? 0)) {
      $end = NULL;
    }

    return ['start' => $start, 'end' => $end];
  }

  /**
   * Converts a time value such as "90", "90s" or "1h2m3s" to seconds.
   *
   * @return int|null
   *   The time in seconds, or NULL if the value is not a positive time.
   */
  protected function toSeconds(mixed $value): ?int {
    if (!is_string($value) || !preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?$/i', $value, $matches)) {
      return NULL;
    }
    $seconds = (int) ($matches[1] ?? 0) * 3600 + (int) ($matches[2] ?? 0) * 60 + (int) ($matches[3] ?? 0);
    return $seconds > 0 ? $seconds : NULL;
  }

  /**
   * Rebuilds a Resource object with new HTML, preserving its other data.
   *
   * Resource is an immutable value object with no HTML setter, so a
   * replacement must be constructed via its named constructor.
   */
  protected function rebuildResource(Resource $resource, string $html): Resource {
    return Resource::video(
      $html,
      $resource->getWidth(),
      $resource->getHeight(),
      $resource->getProvider(),
      $resource->getTitle(),
      $resource->getAuthorName(),
      $resource->getAuthorUrl()?->toString(),
      $resource->getCacheMaxAge(),
      $resource->getThumbnailUrl()?->toString(),
      $resource->getThumbnailWidth(),
      $resource->getThumbnailHeight(),
    );
  }

  /**
   * Extracts a YouTube video ID from an oEmbed endpoint request URL.
   */
  protected function extractYoutubeVideoId(string $oembed_endpoint_url): ?string {
    $video_url = $this->extractOriginalUrl($oembed_endpoint_url);
    if (!$video_url) {
      return NULL;
    }
    if (preg_match('/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:watch\?v=|embed\/|v\/))([A-Za-z0-9_-]{11})/', $video_url, $matches)) {
      return $matches[1];
    }
    return NULL;
  }

  /**
   * Extracts the original video URL from an oEmbed endpoint request URL.
   */
  protected function extractOriginalUrl(string $oembed_endpoint_url): ?string {
    $parts = parse_url($oembed_endpoint_url);
    if (empty($parts['query'])) {
      return NULL;
    }
    parse_str($parts['query'], $query);
    $video_url = $query['url'] ?? NULL;
    return is_string($video_url) && $video_url !== '' ? $video_url : NULL;
  }

  /**
   * Returns the configured YouTube Data API key's value, if any.
   */
  protected function getYoutubeApiKey(): ?string {
    $key_id = $this->configFactory->get('mukurtu_media.settings')->get('youtube_api_key');
    if (!$key_id) {
      return NULL;
    }
    $key = $this->keyRepository->getKey($key_id);
    return $key ? $key->getKeyValue() : NULL;
  }

  /**
   * Builds a Resource for a video by calling the YouTube Data API directly.
   *
   * Used only as a fallback when the standard oEmbed request to YouTube
   * fails outright, e.g. because outbound requests to YouTube are blocked
   * on this host.
   *
   * @param string $video_id
   *   The YouTube video ID.
   * @param array{start: ?int, end: ?int} $segment
   *   The segment to play, as returned by extractSegment().
   */
  protected function fetchFromYoutubeDataApi(string $video_id, array $segment = ['start' => NULL, 'end' => NULL]): ?Resource {
    $api_key = $this->getYoutubeApiKey();
    if (!$api_key) {
      return NULL;
    }

    try {
      $response = $this->httpClient->request('GET', 'https://www.googleapis.com/youtube/v3/videos', [
        RequestOptions::QUERY => [
          'part' => 'snippet',
          'id' => $video_id,
          'key' => $api_key,
        ],
        RequestOptions::TIMEOUT => 5,
      ]);
      $data = Json::decode((string) $response->getBody());
    }
    catch (\Throwable $e) {
      // Deliberately omit $e->getMessage(): Guzzle exception messages
      // include the full request URI, which contains the API key as a
      // query parameter, and would otherwise leak it into watchdog logs.
      $this->logger->warning('YouTube Data API fallback request failed for video @id (@class).', [
        '@id' => $video_id,
        '@class' => get_class($e),
      ]);
      return NULL;
    }

    $snippet = $data['items'][0]['snippet'] ?? NULL;
    if (!$snippet) {
      return NULL;
    }

    $provider = NULL;
    try {
      $provider = $this->providers->get('YouTube');
    }
    catch (\InvalidArgumentException) {
      $provider = NULL;
    }

    $thumbnail = $snippet['thumbnails']['high'] ?? $snippet['thumbnails']['default'] ?? NULL;
    $thumbnail_url = NULL;
    $thumbnail_width = NULL;
    $thumbnail_height = NULL;
    if (!empty($thumbnail['url']) && !empty($thumbnail['width']) && !empty($thumbnail['height'])) {
      $thumbnail_url = $thumbnail['url'];
      $thumbnail_width = $thumbnail['width'];
      $thumbnail_height = $thumbnail['height'];
    }

    $html = sprintf(
      '<iframe width="480" height="270" src="https://www.youtube-nocookie.com/embed/%s?rel=0&modestbranding=1%s" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>',
      $video_id,
      $this->buildYoutubeSegmentParams($segment)
    );

    return Resource::video(
      $html,
      480,
      270,
      $provider,
      $snippet['title'] ?? NULL,
      $snippet['channelTitle'] ?? NULL,
      NULL,
      NULL,
      $thumbnail_url,
      $thumbnail_width,
      $thumbnail_height,
    );
  }

}
