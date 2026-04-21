<?php

namespace Drupal\Tests\search_api\Unit;

use Drupal\search_api\Plugin\search_api\parse_mode\Terms;
use Drupal\Tests\UnitTestCase;

/**
 * Tests functionality of the "Multiple words" parse mode.
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\parse_mode\Terms
 *
 * @group search_api
 */
class TermsParseModeTest extends UnitTestCase {

  /**
   * The parse mode plugin to test.
   *
   * @var \Drupal\search_api\Plugin\search_api\parse_mode\Terms
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->plugin = new Terms([], '', []);
  }

  /**
   * Tests parsing the keys.
   *
   * @param mixed $keys
   *   The keywords to parse.
   * @param array $expected
   *   The expected parsed keys array.
   *
   * @dataProvider parseInputTestDataProvider
   */
  public function testParseInput($keys, array $expected) {
    $parsed = $this->plugin->parseInput($keys);
    $this->assertEquals($expected, $parsed);
  }

  /**
   * Provides test data for testParseInput().
   *
   * @return array[]
   *   An array of argument arrays for testParseInput().
   *
   * @see \Drupal\Tests\search_api\Unit\TermsParseModeTest::testParseInput()
   */
  public static function parseInputTestDataProvider() {
    return [
      'normal keywords' => [
        'keys' => 'foo bar',
        'expected' => [
          '#conjunction' => 'AND',
          'foo',
          'bar',
        ],
      ],
      'quoted phrase' => [
        'keys' => '"cogito ergo sum"',
        'expected' => [
          '#conjunction' => 'AND',
          'cogito ergo sum',
        ],
      ],
      'single-word quotes' => [
        'keys' => '"foo"',
        'expected' => [
          '#conjunction' => 'AND',
          'foo',
        ],
      ],
      'negated keyword' => [
        'keys' => '-foo',
        'expected' => [
          '#conjunction' => 'AND',
          [
            '#negation' => TRUE,
            '#conjunction' => 'AND',
            'foo',
          ],
        ],
      ],
      'negated phrase' => [
        'keys' => '-"cogito ergo sum"',
        'expected' => [
          '#conjunction' => 'AND',
          [
            '#conjunction' => 'AND',
            '#negation' => TRUE,
            'cogito ergo sum',
          ],
        ],
      ],
      'keywords with stand-alone dash' => [
        'keys' => 'foo - bar',
        'expected' => [
          '#conjunction' => 'AND',
          'foo',
          'bar',
        ],
      ],
      'really complicated search' => [
        'keys' => 'pos  -neg "quoted pos with -minus" - -"quoted neg"',
        'expected' => [
          '#conjunction' => 'AND',
          'pos',
          [
            '#negation' => TRUE,
            '#conjunction' => 'AND',
            'neg',
          ],
          'quoted pos with -minus',
          [
            '#negation' => TRUE,
            '#conjunction' => 'AND',
            'quoted neg',
          ],
        ],
      ],
      'multi-byte space' => [
        'keys' => '神奈川県　連携',
        'expected' => [
          '#conjunction' => 'AND',
          '神奈川県',
          '連携',
        ],
      ],
    ];
  }

  /**
   * Tests that invalid UTF-8 in the input string is handled correctly.
   */
  public function testInvalidInput(): void {
    $parsed = $this->plugin->parseInput("\xc3\x28");
    $this->assertEquals([
      '#conjunction' => 'AND',
    ], $parsed);
  }

}
