<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\media\OEmbed\Provider;
use Drupal\media\OEmbed\ProviderRepositoryInterface;
use Drupal\media\OEmbed\Resource;
use Drupal\media\OEmbed\ResourceException;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use Drupal\mukurtu_media\OEmbed\MukurtuResourceFetcher;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;

/**
 * Tests the YouTube privacy rewrite and Data API fallback (issue #565).
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/565
 * @group mukurtu_media
 */
class MukurtuResourceFetcherTest extends UnitTestCase {

  /**
   * The oEmbed endpoint URL used by all tests, encoding a YouTube video URL.
   */
  private const OEMBED_URL = 'https://www.youtube.com/oembed?url=' . 'https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3DdQw4w9WgXcQ' . '&format=json';

  /**
   * Builds a decorator with the given collaborators, defaulting the rest.
   */
  private function createFetcher(
    ResourceFetcherInterface $inner,
    ?ClientInterface $httpClient = NULL,
    ?KeyRepositoryInterface $keyRepository = NULL,
    ?ProviderRepositoryInterface $providers = NULL,
    ?string $configuredKeyId = NULL,
  ): MukurtuResourceFetcher {
    $httpClient ??= $this->createMock(ClientInterface::class);
    $keyRepository ??= $this->createMock(KeyRepositoryInterface::class);
    $providers ??= $this->createMock(ProviderRepositoryInterface::class);
    $logger = $this->createMock(LoggerInterface::class);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('youtube_api_key')->willReturn($configuredKeyId);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('mukurtu_media.settings')->willReturn($config);

    return new MukurtuResourceFetcher($inner, $httpClient, $keyRepository, $providers, $logger, $configFactory);
  }

  /**
   * Builds a YouTube Provider value object.
   */
  private function youtubeProvider(): Provider {
    return new Provider('YouTube', 'https://www.youtube.com/', [
      ['url' => 'https://www.youtube.com/oembed'],
    ]);
  }

  /**
   * A successful fetch of a non-YouTube resource is passed through as-is.
   */
  public function testNonYoutubeResourceIsPassedThroughUnchanged(): void {
    $vimeo = new Provider('Vimeo', 'https://vimeo.com/', [
      ['url' => 'https://vimeo.com/api/oembed.json'],
    ]);
    $original = Resource::video('<iframe src="https://player.vimeo.com/video/123"></iframe>', 640, 360, $vimeo);

    $inner = $this->createMock(ResourceFetcherInterface::class);
    $inner->method('fetchResource')->willReturn($original);

    $fetcher = $this->createFetcher($inner);
    $result = $fetcher->fetchResource(self::OEMBED_URL);

    $this->assertSame($original->getHtml(), $result->getHtml());
  }

  /**
   * A successful YouTube fetch is rewritten to privacy-enhanced mode.
   */
  public function testYoutubeResourceIsRewrittenToNocookie(): void {
    $original = Resource::video(
      '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>',
      640,
      360,
      $this->youtubeProvider(),
      'Test video',
    );

    $inner = $this->createMock(ResourceFetcherInterface::class);
    $inner->method('fetchResource')->willReturn($original);

    $fetcher = $this->createFetcher($inner);
    $result = $fetcher->fetchResource(self::OEMBED_URL);

    $this->assertStringContainsString('www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $result->getHtml());
    $this->assertStringContainsString('rel=0', $result->getHtml());
    $this->assertStringContainsString('modestbranding=1', $result->getHtml());
    $this->assertStringNotContainsString('src="https://www.youtube.com/embed', $result->getHtml());
    $this->assertSame('Test video', $result->getTitle());
  }

  /**
   * When the primary fetch fails and no API key is configured, the original
   * exception propagates rather than a broken resource being fabricated.
   */
  public function testExceptionPropagatesWhenNoApiKeyConfigured(): void {
    $inner = $this->createMock(ResourceFetcherInterface::class);
    $inner->method('fetchResource')->willThrowException(new ResourceException('Could not retrieve the oEmbed resource.', self::OEMBED_URL));

    $fetcher = $this->createFetcher($inner, configuredKeyId: NULL);

    $this->expectException(ResourceException::class);
    $fetcher->fetchResource(self::OEMBED_URL);
  }

  /**
   * When the primary fetch fails, a configured API key lets the fallback
   * build an equivalent, working, privacy-enhanced resource instead.
   */
  public function testFallbackBuildsNocookieResourceWhenPrimaryFetchFails(): void {
    $inner = $this->createMock(ResourceFetcherInterface::class);
    $inner->method('fetchResource')->willThrowException(new ResourceException('Could not retrieve the oEmbed resource.', self::OEMBED_URL));

    $key = $this->createMock(KeyInterface::class);
    $key->method('getKeyValue')->willReturn('test-api-key');
    $keyRepository = $this->createMock(KeyRepositoryInterface::class);
    $keyRepository->method('getKey')->with('yt_key')->willReturn($key);

    $providers = $this->createMock(ProviderRepositoryInterface::class);
    $providers->method('get')->with('YouTube')->willReturn($this->youtubeProvider());

    $apiResponseBody = json_encode([
      'items' => [
        [
          'snippet' => [
            'title' => 'Rick Astley - Never Gonna Give You Up',
            'channelTitle' => 'Rick Astley',
            'thumbnails' => [
              'high' => ['url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', 'width' => 480, 'height' => 360],
            ],
          ],
        ],
      ],
    ]);
    $httpClient = $this->createMock(ClientInterface::class);
    $httpClient->method('request')->willReturn(new Response(200, [], $apiResponseBody));

    $fetcher = $this->createFetcher($inner, $httpClient, $keyRepository, $providers, 'yt_key');
    $result = $fetcher->fetchResource(self::OEMBED_URL);

    $this->assertStringContainsString('www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $result->getHtml());
    $this->assertSame('Rick Astley - Never Gonna Give You Up', $result->getTitle());
  }

  /**
   * If the Data API fallback also fails, the original exception still
   * propagates rather than silently returning a broken resource.
   */
  public function testExceptionPropagatesWhenFallbackAlsoFails(): void {
    $inner = $this->createMock(ResourceFetcherInterface::class);
    $inner->method('fetchResource')->willThrowException(new ResourceException('Could not retrieve the oEmbed resource.', self::OEMBED_URL));

    $key = $this->createMock(KeyInterface::class);
    $key->method('getKeyValue')->willReturn('test-api-key');
    $keyRepository = $this->createMock(KeyRepositoryInterface::class);
    $keyRepository->method('getKey')->with('yt_key')->willReturn($key);

    $httpClient = $this->createMock(ClientInterface::class);
    $httpClient->method('request')->willThrowException(new \GuzzleHttp\Exception\ConnectException(
      'Could not connect',
      new \GuzzleHttp\Psr7\Request('GET', 'https://www.googleapis.com/youtube/v3/videos')
    ));

    $fetcher = $this->createFetcher($inner, $httpClient, $keyRepository, configuredKeyId: 'yt_key');

    $this->expectException(ResourceException::class);
    $fetcher->fetchResource(self::OEMBED_URL);
  }

}
