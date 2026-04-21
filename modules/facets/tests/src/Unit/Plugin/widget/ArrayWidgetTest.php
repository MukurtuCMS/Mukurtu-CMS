<?php

/**
 * @file
 * Tests the ArrayWidget facets widget.
 */

namespace Drupal\Tests\facets\Unit\Plugin\widget;

use Drupal\Core\Url;
use Drupal\facets\Entity\Facet;
use Drupal\facets\Plugin\facets\widget\ArrayWidget;
use Drupal\facets\Result\Result;

/**
 * Unit test for widget.
 *
 * @group facets
 */
class ArrayWidgetTest extends WidgetTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->widget = new ArrayWidget(['show_numbers' => 1], 'array_widget', []);
  }

  /**
   * Tests widget without filters.
   */
  public function testNoFilterResults() {
    $facet = new Facet([], 'facets_facet');
    $facet->setResults($this->originalResults);
    $facet->setFieldIdentifier('tag');

    $output = $this->widget->build($facet);

    $this->assertSame('array', gettype($output));
    $this->assertCount(4, $output['tag']);

    $expected_links = [
      [
        'url' => NULL,
        'raw_value' => 'llama',
        'values' => ['value' => 'Llama', 'count' => 10],
      ],
      [
        'url' => NULL,
        'raw_value' => 'badger',
        'values' => ['value' => 'Badger', 'count' => 20],
      ],
      [
        'url' => NULL,
        'raw_value' => 'duck',
        'values' => ['value' => 'Duck', 'count' => 15],
      ],
      [
        'url' => NULL,
        'raw_value' => 'alpaca',
        'values' => ['value' => 'Alpaca', 'count' => 9],
      ],
    ];
    foreach ($expected_links as $index => $value) {
      $this->assertSame('array', gettype($output['tag'][$index]));
      $this->assertSame($value, $output['tag'][$index]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function testDefaultConfiguration() {
    $default_config = $this->widget->defaultConfiguration();
    $expected = [
      'show_numbers' => FALSE,
    ];
    $this->assertEquals($expected, $default_config);
  }

  /**
   * Tests ArrayWidget build with deep nested results.
   */
  public function testNesting(): void {
    $results_data = [
      '1' => [
        '1.1' => [
          '1.1.1' => [
            '1.1.1.1' => [
              '1.1.1.1.1' => [],
              '1.1.1.1.2' => [],
            ],
          ],
        ],
        '1.2' => [],
        '1.3' => [
          '1.3.1' => [],
        ],
      ],
      '2' => [],
      '3' => [
        '3.1' => [],
      ],
    ];

    $expected_build = [
      [
        'url' => 'http://example.com/1',
        'raw_value' => '1',
        'values' => [
          'value' => 'One',
          'count' => 1,
        ],
        'children' => [
          [
            [
              'url' => 'http://example.com/1.1',
              'raw_value' => '1.1',
              'values' => [
                'value' => 'One.One',
                'count' => 11,
              ],
              'children' => [
                [
                  [
                    'url' => 'http://example.com/1.1.1',
                    'raw_value' => '1.1.1',
                    'values' => [
                      'value' => 'One.One.One',
                      'count' => 111,
                    ],
                    'children' => [
                      [
                        [
                          'url' => 'http://example.com/1.1.1.1',
                          'raw_value' => '1.1.1.1',
                          'values' => [
                            'value' => 'One.One.One.One',
                            'count' => 1111,
                          ],
                          'children' => [
                            [
                              [
                                'url' => 'http://example.com/1.1.1.1.1',
                                'raw_value' => '1.1.1.1.1',
                                'values' => [
                                  'value' => 'One.One.One.One.One',
                                  'count' => 11111,
                                ],
                              ],
                              [
                                'url' => 'http://example.com/1.1.1.1.2',
                                'raw_value' => '1.1.1.1.2',
                                'values' => [
                                  'value' => 'One.One.One.One.Two',
                                  'count' => 11112,
                                ],
                              ],
                            ],
                          ],
                        ],
                      ],
                    ],
                  ],
                ],
              ],
            ],
            [
              'url' => 'http://example.com/1.2',
              'raw_value' => '1.2',
              'values' => [
                'value' => 'One.Two',
                'count' => 12,
              ],
            ],
            [
              'url' => 'http://example.com/1.3',
              'raw_value' => '1.3',
              'values' => [
                'value' => 'One.Three',
                'count' => 13,
              ],
              'children' => [
                [
                  [
                    'url' => 'http://example.com/1.3.1',
                    'raw_value' => '1.3.1',
                    'values' => [
                      'value' => 'One.Three.One',
                      'count' => 131,
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      [
        'url' => 'http://example.com/2',
        'raw_value' => '2',
        'values' => [
          'value' => 'Two',
          'count' => 2,
        ],
      ],
      [
        'url' => 'http://example.com/3',
        'raw_value' => '3',
        'values' => [
          'value' => 'Three',
          'count' => 3,
        ],
        'children' => [
          [
            [
              'url' => 'http://example.com/3.1',
              'raw_value' => '3.1',
              'values' => [
                'value' => 'Three.One',
                'count' => 31,
              ],
            ],
          ],
        ],
      ],
    ];

    $this->facet->setResults($this->buildResults($results_data));
    $this->facet->setFieldIdentifier('tag');

    $this->assertSame($expected_build, $this->widget->build($this->facet)['tag']);
  }

  /**
   * Builds a list of deep nested results.
   *
   * @param array $children
   *   Result data.
   *
   * @return \Drupal\facets\Result\ResultInterface[]
   *   A list of nested results.
   */
  protected function buildResults(array $children): array {
    $results = [];
    foreach ($children as $value => $child) {
      $has_children = !empty($child);
      $value = (string) $value;
      $display_value = str_replace(['1', '2', '3'], ['One', 'Two', 'Three'], $value);
      $count = (int) str_replace('.', '', $value);
      $result = new Result($this->facet, $value, $display_value, $count);
      $result->setUrl(TestUrl::fromUri("http://example.com/{$value}"));
      if ($has_children) {
        $result->setChildren($this->buildResults($child));
      }
      $results[] = $result;
    }
    return $results;
  }

}

/**
 * Mocks \Drupal\Core\Url.
 */
class TestUrl extends Url {

  /**
   * {@inheritdoc}
   */
  protected $uri;

  /**
   * Constructs a new URL instance.
   *
   * @param string $uri
   *   The URI.
   */
  public function __construct(string $uri) {
    $this->uri = $uri;
  }

  /**
   * {@inheritdoc}
   */
  public static function fromUri($uri, $options = []) {
    return new static($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function toString($collect_bubbleable_metadata = FALSE) {
    return $this->uri;
  }

}
