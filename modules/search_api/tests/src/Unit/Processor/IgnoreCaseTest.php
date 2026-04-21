<?php

namespace Drupal\Tests\search_api\Unit\Processor;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Plugin\search_api\processor\IgnoreCase;
use Drupal\search_api\Query\Condition;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "Ignore case" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\IgnoreCase
 */
class IgnoreCaseTest extends UnitTestCase {

  use ProcessorTestTrait;
  use TestItemsTrait;

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->processor = new IgnoreCase([], 'string', []);
  }

  /**
   * Tests the process() method.
   *
   * @param string $passed_value
   *   The value that should be passed into process().
   * @param string $expected_value
   *   The expected processed value.
   *
   * @dataProvider processDataProvider
   */
  public function testProcess($passed_value, $expected_value) {
    $this->invokeMethod('process', [&$passed_value]);
    $this->assertEquals($passed_value, $expected_value);
  }

  /**
   * Provides sets of arguments for testProcess().
   *
   * @return array[]
   *   Arrays of arguments for testProcess().
   */
  public static function processDataProvider() {
    return [
      ['Foo bar', 'foo bar'],
      ['foo Bar', 'foo bar'],
      ['Foo Bar', 'foo bar'],
      // cspell:disable-next-line
      ['Foo bar BaZ, ÄÖÜÀÁ<>»«.', 'foo bar baz, äöüàá<>»«.'],
    ];
  }

  /**
   * Tests whether "IS NULL" conditions are correctly kept.
   *
   * @see https://www.drupal.org/project/search_api/issues/3212925
   */
  public function testIsNullConditions() {
    $this->setUpMockContainer();
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

}
