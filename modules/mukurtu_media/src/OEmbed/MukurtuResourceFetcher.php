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
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/565
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
    try {
      $resource = $this->inner->fetchResource($url);
    }
    catch (ResourceException $e) {
      $video_id = $this->extractYoutubeVideoId($url);
      if ($video_id) {
        $fallback = $this->fetchFromYoutubeDataApi($video_id);
        if ($fallback) {
          return $fallback;
        }
      }
      throw $e;
    }

    if ($this->getProviderName($resource) === 'YouTube' && $resource->getHtml()) {
      return $this->rebuildResource($resource, $this->applyYoutubePrivacyParams($resource->getHtml()));
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
   */
  protected function applyYoutubePrivacyParams(string $html): string {
    return preg_replace_callback(
      '/src="([^"]+)"/',
      function (array $matches): string {
        $src = str_replace(
          ['//www.youtube.com/embed', '//youtube.com/embed'],
          '//www.youtube-nocookie.com/embed',
          $matches[1]
        );
        $separator = str_contains($src, '?') ? '&' : '?';
        return 'src="' . $src . $separator . 'rel=0&modestbranding=1"';
      },
      $html
    );
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
    $parts = parse_url($oembed_endpoint_url);
    if (empty($parts['query'])) {
      return NULL;
    }
    parse_str($parts['query'], $query);
    $video_url = $query['url'] ?? NULL;
    if (!$video_url) {
      return NULL;
    }
    if (preg_match('/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:watch\?v=|embed\/|v\/))([A-Za-z0-9_-]{11})/', $video_url, $matches)) {
      return $matches[1];
    }
    return NULL;
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
   */
  protected function fetchFromYoutubeDataApi(string $video_id): ?Resource {
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
      $this->logger->warning('YouTube Data API fallback request failed for video @id: @message', [
        '@id' => $video_id,
        '@message' => $e->getMessage(),
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
      '<iframe width="480" height="270" src="https://www.youtube-nocookie.com/embed/%s?rel=0&modestbranding=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>',
      $video_id
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
