<?php

namespace Drupal\Tests\twig_tweak\Kernel;

/**
 * A test for URL Extractor service.
 *
 * @group twig_tweak
 */
final class UrlExtractorTest extends AbstractExtractorTestCase {

  /**
   * Test callback.
   */
  public function testUrlExtractor(): void {

    $extractor = $this->container->get('twig_tweak.url_extractor');
    $base_url = \Drupal::service('file_url_generator')->generateAbsoluteString('');

    $request = \Drupal::request();
    $absolute_url = "{$request->getScheme()}://{$request->getHost()}/foo/bar.txt";
    $url = $extractor->extractUrl($absolute_url);
    self::assertSame('/foo/bar.txt', $url);

    $url = $extractor->extractUrl($absolute_url, FALSE);
    self::assertSame($base_url . 'foo/bar.txt', $url);

    $url = $extractor->extractUrl('foo/bar.jpg');
    self::assertSame('/foo/bar.jpg', $url);

    $url = $extractor->extractUrl('foo/bar.jpg', FALSE);
    self::assertSame($base_url . 'foo/bar.jpg', $url);

    $url = $extractor->extractUrl('');
    self::assertSame('/', $url);

    $url = $extractor->extractUrl('', FALSE);
    self::assertSame($base_url, $url);

    $url = $extractor->extractUrl(NULL);
    self::assertNull($url);

    $url = $extractor->extractUrl($this->node);
    self::assertNull($url);

    $url = $extractor->extractUrl($this->node->get('title'));
    self::assertNull($url);

    $url = $extractor->extractUrl($this->node->get('field_image')[0]);
    self::assertStringEndsWith('/files/image-test.png', $url);
    self::assertStringNotContainsString($base_url, $url);

    $url = $extractor->extractUrl($this->node->get('field_image')[0], FALSE);
    self::assertStringStartsWith($base_url, $url);
    self::assertStringEndsWith('/files/image-test.png', $url);

    $url = $extractor->extractUrl($this->node->get('field_image')[1]);
    self::assertNull($url);

    $url = $extractor->extractUrl($this->node->get('field_image'));
    self::assertStringEndsWith('/files/image-test.png', $url);

    $url = $extractor->extractUrl($this->node->get('field_image')->entity);
    self::assertStringEndsWith('/files/image-test.png', $url);

    $this->node->get('field_image')->removeItem(0);
    $url = $extractor->extractUrl($this->node->get('field_image'));
    self::assertNull($url);

    $url = $extractor->extractUrl($this->node->get('field_media')[0]);
    self::assertStringEndsWith('/files/image-test.gif', $url);

    $url = $extractor->extractUrl($this->node->get('field_media')[1]);
    self::assertNull($url);

    $url = $extractor->extractUrl($this->node->get('field_media'));
    self::assertStringEndsWith('/files/image-test.gif', $url);

    $url = $extractor->extractUrl($this->node->get('field_media')->entity);
    self::assertStringEndsWith('/files/image-test.gif', $url);

    $this->node->get('field_media')->removeItem(0);
    $url = $extractor->extractUrl($this->node->get('field_media'));
    self::assertNull($url);
  }

}
