<?php

namespace Drupal\Tests\search_api\Unit\Processor;

use Drupal\Component\Utility\Random;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Plugin\search_api\data_type\value\TextToken;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValue;
use Drupal\search_api\Plugin\search_api\processor\HtmlFilter;
use Drupal\search_api\Query\Condition;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "HTML filter" processor.
 *
 * @group search_api
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\processor\HtmlFilter
 */
class HtmlFilterTest extends UnitTestCase {

  use ProcessorTestTrait;
  use TestItemsTrait;

  /**
   * Creates a new processor object for use in the tests.
   */
  public function setUp(): void {
    parent::setUp();

    $this->setUpMockContainer();

    $this->processor = new HtmlFilter([], 'html_filter', []);
  }

  /**
   * Tests preprocessing field values with different "title" settings.
   *
   * @param string $passed_value
   *   The value that should be passed into process().
   * @param string $expected_value
   *   The expected processed value.
   * @param bool $title_config
   *   The value to set for the processor's "title" setting.
   *
   * @dataProvider titleConfigurationDataProvider
   */
  public function testTitleConfiguration($passed_value, $expected_value, $title_config) {
    $configuration = [
      'tags' => [],
      'title' => $title_config,
      'alt' => FALSE,
    ];
    $this->processor->setConfiguration($configuration);
    $this->invokeMethod('processFieldValue', [&$passed_value, 'text']);
    $this->assertEquals($expected_value, $passed_value);
  }

  /**
   * Data provider for testTitleConfiguration().
   *
   * @return array
   *   An array of argument arrays for testTitleConfiguration().
   */
  public static function titleConfigurationDataProvider() {
    return [
      ['word', 'word', FALSE],
      ['word', 'word', TRUE],
      ['<div>word</div>', 'word', TRUE],
      ['<div title="TITLE">word</div>', 'TITLE word', TRUE],
      ['<div title="TITLE">word</div>', 'word', FALSE],
      ['<div data-title="TITLE">word</div>', 'word', TRUE],
      ['<div title="TITLE">word</a>', 'TITLE word', TRUE],
    ];
  }

  /**
   * Tests preprocessing field values with different "alt" settings.
   *
   * @param string $passed_value
   *   The value that should be passed into process().
   * @param mixed $expected_value
   *   The expected processed value.
   * @param bool $alt_config
   *   The value to set for the processor's "alt" setting.
   *
   * @dataProvider altConfigurationDataProvider
   */
  public function testAltConfiguration($passed_value, $expected_value, $alt_config) {
    $configuration = [
      'tags' => ['img' => '2'],
      'title' => FALSE,
      'alt' => $alt_config,
    ];
    $this->processor->setConfiguration($configuration);
    $this->invokeMethod('processFieldValue', [&$passed_value, 'text']);
    $this->assertEquals($expected_value, $passed_value);
  }

  /**
   * Data provider method for testAltConfiguration().
   *
   * @return array
   *   An array of argument arrays for testAltConfiguration().
   */
  public static function altConfigurationDataProvider() {
    return [
      ['word', [Utility::createTextToken('word')], FALSE],
      ['word', [Utility::createTextToken('word')], TRUE],
      [
        '<img src="href" />word',
        [Utility::createTextToken('word')],
        TRUE,
      ],
      [
        '<img alt="ALT"/> word',
        [
          Utility::createTextToken('ALT', 2),
          Utility::createTextToken('word'),
        ],
        TRUE,
      ],
      [
        '<img alt="ALT" /> word',
        [Utility::createTextToken('word')],
        FALSE,
      ],
      [
        '<img data-alt="ALT"/> word',
        [Utility::createTextToken('word')],
        TRUE,
      ],
      [
        '<img src="href" alt="ALT" title="Bar" /> word </a>',
        [
          Utility::createTextToken('ALT', 2),
          Utility::createTextToken('word'),
        ],
        TRUE,
      ],
      // Test handling of very long tags.
      [
        '<img alt="ALT" src="image/png;base64,' . str_repeat('1', 1000000) . '" /> word </a>',
        [
          Utility::createTextToken('ALT', 2),
          Utility::createTextToken('word'),
        ],
        TRUE,
      ],
    ];
  }

  /**
   * Tests preprocessing field values with different "tags" settings.
   *
   * @param string $passed_value
   *   The value that should be passed into process().
   * @param mixed $expected_value
   *   The expected processed value.
   * @param float[] $tags_config
   *   The value to set for the processor's "tags" setting.
   *
   * @dataProvider tagConfigurationDataProvider
   */
  public function testTagConfiguration($passed_value, $expected_value, array $tags_config) {
    $configuration = [
      'tags' => $tags_config,
      'title' => TRUE,
      'alt' => TRUE,
    ];
    $this->processor->setConfiguration($configuration);
    $this->invokeMethod('processFieldValue', [&$passed_value, 'text']);
    $this->assertEquals($expected_value, $passed_value);
  }

  /**
   * Data provider method for testTagConfiguration().
   *
   * @return array
   *   An array of argument arrays for testTagConfiguration().
   */
  public static function tagConfigurationDataProvider() {
    $tags_config = ['h2' => '2'];
    return [
      ['h2word', 'h2word', []],
      ['h2word', [Utility::createTextToken('h2word')], $tags_config],
      [
        'foo bar <h2> h2word </h2>',
        [
          Utility::createTextToken('foo bar'),
          Utility::createTextToken('h2word', 2.0),
        ],
        $tags_config,
      ],
      [
        'foo bar <h2>h2word</h2>',
        [
          Utility::createTextToken('foo bar'),
          Utility::createTextToken('h2word', 2.0),
        ],
        $tags_config,
      ],
      [
        '<div>word</div>',
        [Utility::createTextToken('word', 2)],
        ['div' => 2],
      ],
      [
        '<h2>Foo Bar <em>Baz</em></h2>

          <p>Bla Bla Bla. <strong title="Foobar">Important:</strong> Bla.</p>
          <img src="image/png;base64,' . str_repeat('1', 1000000) . '" alt="Some picture" />
          <span>This is hidden</span>',
        [
          Utility::createTextToken('Foo Bar', 3.0),
          Utility::createTextToken('Baz', 4.5),
          Utility::createTextToken('Bla Bla Bla.', 1.0),
          Utility::createTextToken('Foobar Important:', 2.0),
          Utility::createTextToken('Bla.', 1.0),
          Utility::createTextToken('Some picture', 0.5),
        ],
        [
          'em' => 1.5,
          'strong' => 2.0,
          'h2' => 3.0,
          'img' => 0.5,
          'span' => 0,
        ],
      ],
      [
        'foo <img src="img.png" alt="image" title = "check this out" /> bar',
        [
          Utility::createTextToken('foo', 1.0),
          Utility::createTextToken('check this out image', 0.5),
          Utility::createTextToken('bar', 1.0),
        ],
        [
          'img' => 0.5,
        ],
      ],
    ];
  }

  /**
   * Tests whether strings are correctly handled.
   *
   * String field handling should be completely independent of configuration.
   *
   * @param array $config
   *   The configuration to set on the processor.
   *
   * @dataProvider stringProcessingDataProvider
   */
  public function testStringProcessing(array $config) {
    $this->processor->setConfiguration($config);

    $passed_value = '<h2>Foo Bar <em>Baz</em></h2>

<p>Bla Bla Bla. <strong title="Foobar">Important:</strong> Bla.</p>
<img src="/foo.png" alt="Some picture" />
<span>This is hidden</span>';
    $expected_value = preg_replace('/\s+/', ' ', strip_tags($passed_value));

    $this->invokeMethod('processFieldValue', [&$passed_value, 'string']);
    $this->assertEquals($expected_value, $passed_value);
  }

  /**
   * Provides a few sets of HTML filter configuration.
   *
   * @return array
   *   An array of argument arrays for testStringProcessing(), where each array
   *   contains a HTML filter configuration as the only value.
   */
  public static function stringProcessingDataProvider() {
    $configs = [];
    $configs[] = [[]];
    $config['tags'] = [
      'h2' => 2.0,
      'span' => 4.0,
      'strong' => 1.5,
      'p' => 0,
    ];
    $configs[] = [$config];
    $config['title'] = TRUE;
    $configs[] = [$config];
    $config['alt'] = TRUE;
    $configs[] = [$config];
    unset($config['tags']);
    $configs[] = [$config];
    return $configs;
  }

  /**
   * Tests whether "IS NULL" conditions are correctly kept.
   *
   * @see https://www.drupal.org/project/search_api/issues/3212925
   */
  public function testIsNullConditions() {
    $index = $this->createMock(IndexInterface::class);
    $index->method('getFields')->willReturn([
      'field' => (new Field($index, 'field'))->setType('string'),
    ]);
    $this->processor->setIndex($index);

    $passed_value = NULL;
    $this->invokeMethod('processConditionValue', [&$passed_value]);
    $this->assertNull($passed_value);

    $condition = new Condition('field', NULL);
    $conditions = [$condition];
    $this->invokeMethod('processConditions', [&$conditions]);
    $this->assertSame([$condition], $conditions);
  }

  /**
   * Tests empty values handling.
   *
   * @see https://www.drupal.org/project/search_api/issues/3212925
   */
  public function testEmptyValueHandling() {
    $index = $this->createMock(IndexInterface::class);
    $field = (new Field($index, 'field'))
      ->setType('text');
    $index->method('getFields')->willReturn([
      'field' => $field,
    ]);

    $field->setValues([new TextValue('<p></p>')]);
    $this->invokeMethod('processField', [$field]);
    $this->assertEquals([], $field->getValues());

    $value = new TextValue('<p></p>');
    $value->setTokens([new TextToken('<p></p>')]);
    $field->setValues([$value]);
    $this->invokeMethod('processField', [$field]);
    $this->assertEquals([], $field->getValues());

    // In theory, setting the value of a "text" field to a string instead of a
    // TextValue object is not allowed, but when it happens we might as well
    // handle it graciously (though other parts of the framework might not).
    $field->setValues(['<p></p>']);
    $this->invokeMethod('processField', [$field]);
    $this->assertEquals([], $field->getValues());

    $field->setType('string');
    $field->setValues(['<p></p>']);
    $this->invokeMethod('processField', [$field]);
    $this->assertEquals([], $field->getValues());
  }

  /**
   * Tests that attribute handling is still fast even for large text values.
   *
   * @see https://www.drupal.org/project/search_api/issues/3388678
   */
  public function testLargeTextAttributesHandling(): void {
    $this->processor->setConfiguration([
      'tags' => [
        'em' => 1.5,
        'strong' => 2.0,
        'h2' => 3.0,
        'img' => 0.5,
        'span' => 0,
      ],
      'title' => TRUE,
      'alt' => TRUE,
    ]);
    $text = '';
    $random = new Random();
    for ($i = 0; $i < 2000; ++$i) {
      $text .= ' ' . htmlspecialchars($random->sentences(10));
      $tag = $random->name();
      $attr = $random->name();
      $value = htmlspecialchars($random->word(12));
      $contents = htmlspecialchars($random->sentences(10));
      $text .= " <$tag $attr=\"$value\">$contents</$tag>";
    }
    $start = microtime(TRUE);
    $this->invokeMethod('processFieldValue', [&$text, 'text']);
    $took = microtime(TRUE) - $start;
    $this->assertLessThan(1.0, $took, 'Processing large field value took too long.');
  }

  /**
   * Tests that invisible HTML elements are correctly removed.
   *
   * @covers ::removeInvisibleHtmlElements
   */
  public function testRemoveInvisibleHtmlElements(): void {
    $filler_html = str_repeat("<p><strong>Something.</strong></p>\n", 100000);
    /** @noinspection HtmlUnknownTarget */
    $passed_value = <<<HTML
      <p>Foo <em>bar</em> baz</p>
      <script type="text/javascript">
        document.title = "Something";
        alert("Bar");
      </script><style>a { font-style: italic; }</style>
      <p>Bla.</p>
      <embed type="video/webm" src="/videos/flower.mp4" width="250" height="200" />
      <p>Further text.</p>
      <script>alert('Foo');</script>
      <video controls width="250">
        <source src="/videos/flower.webm" type="video/webm" />
        <source src="/videos/flower.mp4" type="video/mp4" />
        Download the <a href="/videos/flower.mp4">video</a>.
        $filler_html
      </video>
      <p>The end.</p>
</script>
HTML;
    $expected_value = 'Foo bar baz Bla. Further text. The end.';
    $configuration = [
      'tags' => [],
      'title' => TRUE,
      'alt' => TRUE,
    ];
    $this->processor->setConfiguration($configuration);
    $this->invokeMethod('processFieldValue', [&$passed_value, 'text']);
    $this->assertEquals($expected_value, $passed_value);
  }

}
