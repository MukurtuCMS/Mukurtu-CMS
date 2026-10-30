<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\media\IFrameMarkup;
use Drupal\mukurtu_media\Hook\OEmbedIframeHooks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that a Vimeo #t= start time stays at the end of the player src.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/557
 */
#[Group('mukurtu_media')]
class OEmbedIframeHooksTest extends UnitTestCase {

  /**
   * Data provider for testPreprocessMediaOembedIframe().
   *
   * @return array<string, array{string, string}>
   *   The iframe page media HTML, and the expected HTML after preprocessing.
   */
  public static function mediaProvider(): array {
    return [
      'Blazy autoplay after the fragment, escaped' => [
        '<iframe src="https://player.vimeo.com/video/1084537?app_id=122963#t=5s&amp;autoplay=1"></iframe>',
        '<iframe src="https://player.vimeo.com/video/1084537?app_id=122963&amp;autoplay=1#t=5s"></iframe>',
      ],
      'Blazy autoplay after the fragment, unescaped' => [
        '<iframe src="https://player.vimeo.com/video/1084537?app_id=122963#t=5s&autoplay=1"></iframe>',
        '<iframe src="https://player.vimeo.com/video/1084537?app_id=122963&autoplay=1#t=5s"></iframe>',
      ],
      'no query string on the src' => [
        '<iframe src="https://player.vimeo.com/video/1084537#t=5s&amp;autoplay=1"></iframe>',
        '<iframe src="https://player.vimeo.com/video/1084537?autoplay=1#t=5s"></iframe>',
      ],
      'start time alone is unchanged' => [
        '<iframe src="https://player.vimeo.com/video/1084537?app_id=122963#t=5s"></iframe>',
        '<iframe src="https://player.vimeo.com/video/1084537?app_id=122963#t=5s"></iframe>',
      ],
      'YouTube is unchanged' => [
        '<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=5&amp;autoplay=1"></iframe>',
        '<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=5&amp;autoplay=1"></iframe>',
      ],
    ];
  }

  /**
   * Parameters appended after a #t= fragment are moved into the query.
   */
  #[DataProvider('mediaProvider')]
  public function testPreprocessMediaOembedIframe(string $media, string $expected): void {
    $variables = ['media' => IFrameMarkup::create($media)];

    (new OEmbedIframeHooks())->preprocessMediaOembedIframe($variables);

    $this->assertSame($expected, (string) $variables['media']);
  }

  /**
   * An empty iframe page, such as a failed fetch, is left alone.
   */
  public function testEmptyMediaIsLeftAlone(): void {
    $variables = ['media' => ''];

    (new OEmbedIframeHooks())->preprocessMediaOembedIframe($variables);

    $this->assertSame('', $variables['media']);
  }

}
