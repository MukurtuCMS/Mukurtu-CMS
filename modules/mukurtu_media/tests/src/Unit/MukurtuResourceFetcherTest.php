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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests the oEmbed resource fetcher's YouTube and Vimeo handling.
 *
 * Covers the YouTube privacy rewrite and Data API fallback (issue #565), and
 * segment start and end times (issues #557 and #2548).
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/565
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/557
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2548
 */
#[Group('mukurtu_media')]
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
   * Builds an oEmbed endpoint URL that encodes the given video URL.
   */
  private static function oembedUrl(string $endpoint, string $video_url): string {
    return $endpoint . '?url=' . rawurlencode($video_url) . '&format=json';
  }

  /**
   * Builds a decorator whose inner fetcher returns the given resource.
   */
  private function createFetcherReturning(Resource $resource): MukurtuResourceFetcher {
    $inner = $this->createMock(ResourceFetcherInterface::class);
    $inner->method('fetchResource')->willReturn($resource);
    return $this->createFetcher($inner);
  }

  /**
   * Builds a Vimeo Provider value object.
   */
  private function vimeoProvider(): Provider {
    return new Provider('Vimeo', 'https://vimeo.com/', [
      ['url' => 'https://vimeo.com/api/oembed.json'],
    ]);
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

  /**
   * Data provider for testYoutubeSegmentIsAppliedToEmbed().
   *
   * @return array<string, array{string, string}>
   *   The video URL, and the expected iframe src query string.
   */
  public static function youtubeSegmentProvider(): array {
    $base = 'feature=oembed&rel=0&modestbranding=1';
    $watch = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
    return [
      'no segment' => [$watch, $base],
      'short link, seconds' => ['https://youtu.be/dQw4w9WgXcQ?t=90', $base . '&start=90'],
      'seconds with suffix' => ['https://youtu.be/dQw4w9WgXcQ?si=abc&t=90s', $base . '&start=90'],
      'minutes and seconds' => [$watch . '&t=1m30s', $base . '&start=90'],
      'hours, minutes and seconds' => [$watch . '&t=1h2m3s', $base . '&start=3723'],
      'fragment' => [$watch . '#t=45', $base . '&start=45'],
      'start and end' => [$watch . '&start=30&end=45', $base . '&start=30&end=45'],
      'end only' => [$watch . '&end=45', $base . '&end=45'],
      'end in fragment' => [$watch . '#t=30&end=45', $base . '&start=30&end=45'],
      'start takes precedence over t' => [$watch . '&t=10&start=30', $base . '&start=30'],
      'end not after start is ignored' => [$watch . '&start=45&end=30', $base . '&start=45'],
      'invalid value is ignored' => ['https://youtu.be/dQw4w9WgXcQ?t=abc', $base],
      'zero is ignored' => ['https://youtu.be/dQw4w9WgXcQ?t=0', $base],
    ];
  }

  /**
   * A YouTube video URL's segment is carried onto the embed's src.
   */
  #[DataProvider('youtubeSegmentProvider')]
  public function testYoutubeSegmentIsAppliedToEmbed(string $video_url, string $expected_query): void {
    $original = Resource::video(
      '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?feature=oembed"></iframe>',
      640,
      360,
      $this->youtubeProvider(),
    );

    $fetcher = $this->createFetcherReturning($original);
    $result = $fetcher->fetchResource(self::oembedUrl('https://www.youtube.com/oembed', $video_url));

    $this->assertSame(
      '<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?' . $expected_query . '"></iframe>',
      $result->getHtml()
    );
  }

  /**
   * A start time YouTube already put on the src is not added a second time.
   */
  public function testYoutubeStartIsNotDuplicated(): void {
    $original = Resource::video(
      '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?start=30&feature=oembed"></iframe>',
      640,
      360,
      $this->youtubeProvider(),
    );

    $fetcher = $this->createFetcherReturning($original);
    $video_url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&start=30&end=45';
    $result = $fetcher->fetchResource(self::oembedUrl('https://www.youtube.com/oembed', $video_url));

    $this->assertSame(
      '<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=30&feature=oembed&rel=0&modestbranding=1&end=45"></iframe>',
      $result->getHtml()
    );
  }

  /**
   * A Vimeo video URL's start time is carried onto the embed as #t=.
   */
  public function testVimeoStartTimeIsAppliedToEmbed(): void {
    $original = Resource::video(
      '<iframe src="https://player.vimeo.com/video/76979871?h=8272103f6e&amp;app_id=122963"></iframe>',
      640,
      360,
      $this->vimeoProvider(),
      'Test video',
    );

    $fetcher = $this->createFetcherReturning($original);
    $result = $fetcher->fetchResource(self::oembedUrl('https://vimeo.com/api/oembed.json', 'https://vimeo.com/76979871#t=1m30s'));

    $this->assertSame(
      '<iframe src="https://player.vimeo.com/video/76979871?h=8272103f6e&amp;app_id=122963#t=90s"></iframe>',
      $result->getHtml()
    );
    $this->assertSame('Test video', $result->getTitle());
  }

  /**
   * Data provider for testVimeoEndTimeAddsPauseScript().
   *
   * @return array<string, array{string, string}>
   *   The video URL, and the expected iframe src.
   */
  public static function vimeoEndTimeProvider(): array {
    return [
      'query' => ['https://vimeo.com/76979871?t=30&end=45', 'https://player.vimeo.com/video/76979871#t=30s'],
      'fragment' => ['https://vimeo.com/76979871#t=30s&end=45s', 'https://player.vimeo.com/video/76979871#t=30s'],
      'end only' => ['https://vimeo.com/76979871?end=45', 'https://player.vimeo.com/video/76979871'],
    ];
  }

  /**
   * Vimeo's embed has no end time, so a script pauses the player there.
   */
  #[DataProvider('vimeoEndTimeProvider')]
  public function testVimeoEndTimeAddsPauseScript(string $video_url, string $expected_src): void {
    $original = Resource::video('<iframe src="https://player.vimeo.com/video/76979871"></iframe>', 640, 360, $this->vimeoProvider());

    $fetcher = $this->createFetcherReturning($original);
    $html = $fetcher->fetchResource(self::oembedUrl('https://vimeo.com/api/oembed.json', $video_url))->getHtml();

    $this->assertStringStartsWith('<iframe data-mukurtu-end="45" src="' . $expected_src . '"></iframe><script>', $html);
    $this->assertStringContainsString("method: 'pause'", $html);
    $this->assertStringEndsWith('</script>', $html);
  }

  /**
   * A Vimeo start time alone adds no pause script.
   */
  public function testVimeoStartTimeAloneAddsNoScript(): void {
    $original = Resource::video('<iframe src="https://player.vimeo.com/video/76979871"></iframe>', 640, 360, $this->vimeoProvider());

    $fetcher = $this->createFetcherReturning($original);
    $html = $fetcher->fetchResource(self::oembedUrl('https://vimeo.com/api/oembed.json', 'https://vimeo.com/76979871#t=30s'))->getHtml();

    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringNotContainsString('data-mukurtu-end', $html);
  }

  /**
   * A Vimeo video URL without a start time is passed through unchanged.
   */
  public function testVimeoWithoutSegmentIsPassedThroughUnchanged(): void {
    $original = Resource::video('<iframe src="https://player.vimeo.com/video/76979871"></iframe>', 640, 360, $this->vimeoProvider());

    $fetcher = $this->createFetcherReturning($original);
    $result = $fetcher->fetchResource(self::oembedUrl('https://vimeo.com/api/oembed.json', 'https://vimeo.com/76979871'));

    $this->assertSame($original, $result);
  }

  /**
   * The Data API fallback also carries the segment onto the embed's src.
   */
  public function testFallbackAppliesSegment(): void {
    $oembed_url = self::oembedUrl('https://www.youtube.com/oembed', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&start=30&end=45');
    $inner = $this->createMock(ResourceFetcherInterface::class);
    $inner->method('fetchResource')->willThrowException(new ResourceException('Could not retrieve the oEmbed resource.', $oembed_url));

    $key = $this->createMock(KeyInterface::class);
    $key->method('getKeyValue')->willReturn('test-api-key');
    $keyRepository = $this->createMock(KeyRepositoryInterface::class);
    $keyRepository->method('getKey')->with('yt_key')->willReturn($key);

    $httpClient = $this->createMock(ClientInterface::class);
    $httpClient->method('request')->willReturn(new Response(200, [], json_encode([
      'items' => [['snippet' => ['title' => 'Test video']]],
    ])));

    $fetcher = $this->createFetcher($inner, $httpClient, $keyRepository, configuredKeyId: 'yt_key');
    $result = $fetcher->fetchResource($oembed_url);

    $this->assertStringContainsString('www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&modestbranding=1&start=30&end=45"', $result->getHtml());
  }

}
