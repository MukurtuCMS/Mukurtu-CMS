<?php

namespace Drupal\Tests\twig_tweak\Kernel;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * A test for Cache Metadata Extractor service.
 *
 * @group twig_tweak
 */
final class CacheMetadataExtractorTest extends AbstractTestCase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'twig_tweak',
  ];

  /**
   * Test callback.
   */
  public function testCacheMetadataExtractor(): void {

    $extractor = $this->container->get('twig_tweak.cache_metadata_extractor');

    // -- Object.
    $input = new CacheableMetadata();
    $input->setCacheMaxAge(5);
    $input->setCacheContexts(['url', 'user.permissions']);
    $input->setCacheTags(['node', 'node.view']);

    $build = $extractor->extractCacheMetadata($input);
    $expected_build['#cache'] = [
      'contexts' => ['url', 'user.permissions'],
      'tags' => ['node', 'node.view'],
      'max-age' => 5,
    ];
    self::assertSame($expected_build, $build);

    // -- Render array.
    $input = [
      'foo' => [
        '#cache' => [
          'tags' => ['foo', 'foo.view'],
        ],
        'bar' => [
          0 => [
            '#cache' => [
              'tags' => ['bar-0'],
            ],
          ],
          1 => [
            '#cache' => [
              'tags' => ['bar-1'],
            ],
          ],
          '#cache' => [
            'tags' => ['bar', 'bar.view'],
            'contexts' => ['url.path'],
            'max-age' => 10,
          ],
        ],
      ],
      '#cache' => [
        'contexts' => ['url', 'user.permissions'],
        'tags' => ['node', 'node.view'],
        'max-age' => 20,
      ],
    ];
    $build = $extractor->extractCacheMetadata($input);

    $expected_build = [
      '#cache' => [
        'contexts' => ['url', 'url.path', 'user.permissions'],
        'tags' => [
          'bar',
          'bar-0',
          'bar-1',
          'bar.view',
          'foo',
          'foo.view',
          'node',
          'node.view',
        ],
        'max-age' => 10,
      ],
    ];
    self::assertRenderArray($expected_build, $build);

    // -- Wrong type.
    $exception = new \InvalidArgumentException('The input should be either instance of Drupal\Core\Cache\CacheableDependencyInterface or array. stdClass was given.');
    self::expectExceptionObject($exception);
    /* @noinspection PhpParamsInspection */
    $extractor->extractCacheMetadata(new \stdClass());
  }

}
