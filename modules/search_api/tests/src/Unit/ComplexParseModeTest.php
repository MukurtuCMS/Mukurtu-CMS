<?php

namespace Drupal\Tests\search_api\Unit;

use Drupal\search_api\Plugin\search_api\parse_mode\Complex;
use Drupal\Tests\UnitTestCase;

/**
 * Tests functionality of the "Complex" parse mode.
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\parse_mode\Complex
 *
 * @group search_api
 */
class ComplexParseModeTest extends UnitTestCase {

  /**
   * The parse mode plugin to test.
   *
   * @var \Drupal\search_api\Plugin\search_api\parse_mode\Complex
   */
  protected Complex $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->plugin = new Complex([], '', []);
  }

  /**
   * Tests parsing the keys.
   *
   * @param string|array $keys
   *   The keywords to parse.
   * @param array|null $expected
   *   The expected parsed keys array.
   * @param array|null $expected_ids
   *   (optional) Ignored.
   *
   * @dataProvider \Drupal\Tests\search_api\Kernel\ComplexParseModeSearchTest::complexKeywordsSearchesTestDataProvider
   */
  public function testParseInput(string|array $keys, ?array $expected, ?array $expected_ids = NULL): void {
    $parsed = $this->plugin->parseInput($keys);
    $this->assertEquals($expected, $parsed);
  }

  /**
   * Tests that invalid UTF-8 in the input string is handled correctly.
   */
  public function testInvalidInput(): void {
    $parsed = $this->plugin->parseInput("\xc3\x28");
    $this->assertNull($parsed);
  }

}
