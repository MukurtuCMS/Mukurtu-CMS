<?php

namespace Drupal\Tests\twig_tweak\Kernel;

/**
 * A test for URI extractor service.
 *
 * @group twig_tweak
 */
final class UriExtractorTest extends AbstractExtractorTestCase {

  /**
   * Test callback.
   */
  public function testUriExtractor(): void {

    $extractor = $this->container->get('twig_tweak.uri_extractor');

    $url = $extractor->extractUri(NULL);
    self::assertNull($url);

    $url = $extractor->extractUri($this->node);
    self::assertNull($url);

    $url = $extractor->extractUri($this->node->get('title'));
    self::assertNull($url);

    $url = $extractor->extractUri($this->node->get('field_image')[0]);
    self::assertSame('public://image-test.png', $url);

    $url = $extractor->extractUri($this->node->get('field_image')[1]);
    self::assertNull($url);

    $url = $extractor->extractUri($this->node->get('field_image'));
    self::assertSame('public://image-test.png', $url);

    $url = $extractor->extractUri($this->node->get('field_image')->entity);
    self::assertSame('public://image-test.png', $url);

    $this->node->get('field_image')->removeItem(0);
    $url = $extractor->extractUri($this->node->get('field_image'));
    self::assertNull($url);

    $url = $extractor->extractUri($this->node->get('field_media')[0]);
    self::assertSame('public://image-test.gif', $url);

    $url = $extractor->extractUri($this->node->get('field_media')[1]);
    self::assertNull($url);

    $url = $extractor->extractUri($this->node->get('field_media'));
    self::assertSame('public://image-test.gif', $url);

    $url = $extractor->extractUri($this->node->get('field_media')->entity);
    self::assertSame('public://image-test.gif', $url);

    $this->node->get('field_media')->removeItem(0);
    $url = $extractor->extractUri($this->node->get('field_media'));
    self::assertNull($url);
  }

}
