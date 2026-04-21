<?php

namespace Drupal\Tests\better_exposed_filters\Unit;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\UnitTestCase;
use Drupal\better_exposed_filters\BetterExposedFiltersHelper;

/**
 * Tests the helper functions for better exposed filters.
 *
 * @coversDefaultClass \Drupal\better_exposed_filters\BetterExposedFiltersHelper
 *
 * @group better_exposed_filters
 */
class BetterExposedFiltersHelperUnitTest extends UnitTestCase {

  use StringTranslationTrait;

  /**
   * Tests options are rewritten correctly.
   *
   * @dataProvider providerTestRewriteOptions
   *
   * @covers ::rewriteOptions
   */
  public function testRewriteOptions($options, $settings, $expected, $rewrite_based_on_key = FALSE) {
    $actual = BetterExposedFiltersHelper::rewriteOptions($options, $settings, FALSE, $rewrite_based_on_key);
    $this->assertEquals(array_values($expected), array_values($actual));
  }

  /**
   * Data provider for ::testRewriteOptions.
   */
  public static function providerTestRewriteOptions(): array {
    $data = [];

    // Super basic rewrite.
    $data[] = [
      ['foo' => 'bar'],
      "bar|baz",
      ['foo' => 'baz'],
    ];

    // Removes an option.
    $data[] = [
      ['foo' => 'bar'],
      "bar|",
      [],
    ];

    // An option in the middle is removed -- preserves order.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      "2|",
      ['foo' => '1', 'baz' => '3'],
    ];

    // Check boolean values.
    $data[] = [
      ['foo' => '0', 'bar' => '1'],
      "0|Zero",
      ['foo' => 'Zero', 'bar' => '1'],
    ];

    $data[] = [
      ['foo' => '0', 'bar' => '1'],
      "1|One",
      ['foo' => '0', 'bar' => 'One'],
    ];

    // Ensure order is preserved.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      "2|Two",
      ['foo' => '1', 'bar' => 'Two', 'baz' => '3'],
    ];

    // No options are replaced.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      "4|Two",
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
    ];

    // All options are replaced.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      "1|One\n2|Two\n3|Three",
      ['foo' => 'One', 'bar' => 'Two', 'baz' => 'Three'],
    ];

    // Key based option replacement - no options are replaced.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      "4|Two",
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      TRUE,
    ];

    // Key based option replacement - some options are replaced.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      "foo|One\n2|Two\nbaz|Three",
      [
        'foo' => new TranslatableMarkup('One'),
        'bar' => '2',
        'baz' => new TranslatableMarkup('Three'),
      ],
      TRUE,
    ];

    // Key based option replacement - all options are replaced.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      "foo|One\nbar|Two\nbaz|Three",
      [
        'foo' => new TranslatableMarkup('One'),
        'bar' => new TranslatableMarkup('Two'),
        'baz' => new TranslatableMarkup('Three'),
      ],
      TRUE,
    ];

    return $data;
  }

  /**
   * Tests options are rewritten correctly.
   *
   * @dataProvider providerTestRewriteReorderOptions
   *
   * @covers ::rewriteOptions
   */
  public function testRewriteReorderOptions($options, $settings, $expected) {
    $actual = BetterExposedFiltersHelper::rewriteOptions($options, $settings, TRUE);
    $this->assertEquals(array_values($expected), array_values($actual));
  }

  /**
   * Data provider for ::testRewriteReorderOptions.
   */
  public static function providerTestRewriteReorderOptions(): array {
    $data = [];

    // Basic use case.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      '2|Two',
      ['bar' => 'Two', 'foo' => '1', 'baz' => '3'],
    ];

    // No option replaced should not change the order.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      '4|Four',
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
    ];

    // Completely reorder options.
    $data[] = [
      ['foo' => '1', 'bar' => '2', 'baz' => '3'],
      "3|Three\n2|Two\n1|One",
      ['baz' => 'Three', 'bar' => 'Two', 'foo' => 'One'],
    ];

    return $data;
  }

  /**
   * Tests options are rewritten correctly.
   *
   * @dataProvider providerTestRewriteTaxonomy
   *
   * @covers ::rewriteOptions
   */
  public function testRewriteTaxonomy($options, $settings, $expected, $reorder = FALSE) {
    $actual = BetterExposedFiltersHelper::rewriteOptions($options, $settings, $reorder);
    $this->assertEquals(array_values($expected), array_values($actual));
  }

  /**
   * Data provider for ::testRewriteTaxonomy.
   */
  public static function providerTestRewriteTaxonomy(): array {
    $data = [];

    // Replace a single item, no change in order.
    $data[] = [
      [
        (object) ['option' => [123 => 'term1']],
        (object) ['option' => [456 => 'term2']],
        (object) ['option' => [789 => 'term3']],
      ],
      "term2|Two",
      [
        (object) ['option' => [123 => 'term1']],
        (object) ['option' => [456 => 'Two']],
        (object) ['option' => [789 => 'term3']],
      ],
    ];

    // Replace all items, no change in order.
    $data[] = [
      [
        (object) ['option' => [123 => 'term1']],
        (object) ['option' => [456 => 'term2']],
        (object) ['option' => [789 => 'term3']],
      ],
      "term2|Two\nterm3|Three\nterm1|One",
      [
        (object) ['option' => [123 => 'One']],
        (object) ['option' => [456 => 'Two']],
        (object) ['option' => [789 => 'Three']],
      ],
    ];

    // Replace a single item, with change in order.
    $data[] = [
     [
       (object) ['option' => [123 => 'term1']],
       (object) ['option' => [456 => 'term2']],
       (object) ['option' => [789 => 'term3']],
     ], "term2|Two",
     [
       (object) ['option' => [456 => 'Two']],
       (object) ['option' => [123 => 'term1']],
       (object) ['option' => [789 => 'term3']],
     ], TRUE,
    ];

    // Replace all items, with change in order.
    $data[] = [
     [
       (object) ['option' => [123 => 'term1']],
       (object) ['option' => [456 => 'term2']],
       (object) ['option' => [789 => 'term3']],
     ], "term2|Two\nterm3|Three\nterm1|One",
     [
       (object) ['option' => [456 => 'Two']],
       (object) ['option' => [789 => 'Three']],
       (object) ['option' => [123 => 'One']],
     ], TRUE,
    ];

    return $data;
  }

  /**
   * Tests options are rewritten correctly.
   *
   * @dataProvider providerTestSortOptions
   *
   * @covers ::sortOptions
   *
   * @throws \PHPUnit\Framework\MockObject\Exception
   */
  public function testSortOptions($unsorted, $expected) {
    // Data providers run before ::setUp. We rely on the stringTranslationTrait
    // for some of our option values so call it here instead.
    $this->stringTranslation = $this->getStringTranslationStub();

    $transliterator = $this->createMock(TransliterationInterface::class);
    $transliterator->expects($this->any())
      ->method('transliterate')
      ->willReturnCallback(
        fn($string, $langcode = 'en', $unknown_character = '?', $max_length = NULL) => str_replace([
          'á',
          'ã',
          'è',
          'ë',
          'ő',
        ], ['a', 'a', 'e', 'e', 'o'], $string)
      );
    $container = new ContainerBuilder();
    $container->set('transliteration', $transliterator);
    \Drupal::setContainer($container);

    $sorted = BetterExposedFiltersHelper::sortOptions($unsorted);
    $this->assertEquals(array_values($sorted), array_values($expected));
  }

  /**
   * Data provider for ::testSortOptions.
   */
  public static function providerTestSortOptions(): array {
    $data = [];

    // List of strings.
    $data[] = [
      [
        'e',
        'a',
        'b',
        'd',
        'c',
      ], [
        'a',
        'b',
        'c',
        'd',
        'e',
      ],
    ];

    // List of mixed values.
    $data[] = [
      [
        '1',
        'a',
        '2',
        'b',
        '3',
      ], [
        '1',
        '2',
        '3',
        'a',
        'b',
      ],
    ];

    // List of strings that need transliteration.
    $data[] = [
      [
        'ë',
        'á',
        'b',
        'd',
        'c',
      ], [
        'á',
        'b',
        'c',
        'd',
        'ë',
      ],
    ];

    // List of mixed values that need transliteration.
    $data[] = [
      [
        '1',
        'ã',
        '2',
        'b',
        '3',
      ], [
        '1',
        '2',
        '3',
        'ã',
        'b',
      ],
    ];

    // List of taxonomy terms.
    $data[] = [
      [
        (object) ['option' => [555 => 'term5']],
        (object) ['option' => [222 => 'term2']],
        (object) ['option' => [444 => 'term4']],
        (object) ['option' => [333 => 'tèrm3']],
        (object) ['option' => [111 => 'term1']],
      ], [
        (object) ['option' => [111 => 'term1']],
        (object) ['option' => [222 => 'term2']],
        (object) ['option' => [333 => 'tèrm3']],
        (object) ['option' => [444 => 'term4']],
        (object) ['option' => [555 => 'term5']],
      ],
    ];

    return $data;
  }

  /**
   * Tests empty options handling.
   *
   * @covers ::sortOptions
   */
  public function testEmptyOptions() {
    $sorted = BetterExposedFiltersHelper::sortOptions([]);
    $this->assertEmpty($sorted, 'Empty options array remains unchanged');
  }

  /**
   * Tests the enhanced sortOptionsCustom method with various sorting methods.
   *
   * @dataProvider providerTestSortOptionsCustom
   *
   * @covers ::sortOptionsCustom
   *
   * @throws \PHPUnit\Framework\MockObject\Exception
   */
  public function testSortOptionsCustom($unsorted, $method, $direction, $natural, $expected) {
    // Data providers run before ::setUp. We rely on the stringTranslationTrait
    // for some of our option values so call it here instead.
    $this->stringTranslation = $this->getStringTranslationStub();

    $transliterator = $this->createMock(TransliterationInterface::class);
    $transliterator->expects($this->any())
      ->method('transliterate')
      ->willReturnCallback(
        fn($string, $langcode = 'en', $unknown_character = '?', $max_length = NULL) => str_replace([
          'á',
          'ã',
          'è',
          'ë',
          'ő',
        ], ['a', 'a', 'e', 'e', 'o'], $string)
      );
    $container = new ContainerBuilder();
    $container->set('transliteration', $transliterator);
    \Drupal::setContainer($container);

    $sorted = BetterExposedFiltersHelper::sortOptionsCustom($unsorted, $method, $direction, $natural);
    $this->assertEquals(array_values($expected), array_values($sorted));
  }

  /**
   * Data provider for ::testSortOptionsCustom.
   */
  public static function providerTestSortOptionsCustom(): array {
    $data = [];

    // Test alphabetical ascending with natural sorting.
    $data[] = [
      [
        'item10' => 'Item 10',
        'item2' => 'Item 2',
        'item1' => 'Item 1',
        'item20' => 'Item 20',
      ],
      'alpha',
      'asc',
      TRUE,
      [
        'item1' => 'Item 1',
        'item2' => 'Item 2',
        'item10' => 'Item 10',
        'item20' => 'Item 20',
      ],
    ];

    // Test alphabetical ascending without natural sorting.
    $data[] = [
      [
        'item10' => 'Item 10',
        'item2' => 'Item 2',
        'item1' => 'Item 1',
        'item20' => 'Item 20',
      ],
      'alpha',
      'asc',
      FALSE,
      [
        'item1' => 'Item 1',
        'item10' => 'Item 10',
        'item2' => 'Item 2',
        'item20' => 'Item 20',
      ],
    ];

    // Test alphabetical descending.
    $data[] = [
      [
        'a' => 'Apple',
        'c' => 'Cherry',
        'b' => 'Banana',
      ],
      'alpha',
      'desc',
      TRUE,
      [
        'c' => 'Cherry',
        'b' => 'Banana',
        'a' => 'Apple',
      ],
    ];

    // Test key ascending.
    $data[] = [
      [
        '30' => 'Value 30',
        '10' => 'Value 10',
        '20' => 'Value 20',
      ],
      'key',
      'asc',
      TRUE,
      [
        '10' => 'Value 10',
        '20' => 'Value 20',
        '30' => 'Value 30',
      ],
    ];

    // Test key descending.
    $data[] = [
      [
        '10' => 'Value 10',
        '30' => 'Value 30',
        '20' => 'Value 20',
      ],
      'key',
      'desc',
      TRUE,
      [
        '30' => 'Value 30',
        '20' => 'Value 20',
        '10' => 'Value 10',
      ],
    ];

    return $data;
  }

  /**
   * Tests the sortOptionsByKey method.
   *
   * @dataProvider providerTestSortOptionsByKey
   *
   * @covers ::sortOptionsByKey
   */
  public function testSortOptionsByKey($unsorted, $direction, $expected) {
    $sorted = BetterExposedFiltersHelper::sortOptionsByKey($unsorted, $direction);
    $this->assertEquals($expected, $sorted);
  }

  /**
   * Data provider for ::testSortOptionsByKey.
   */
  public static function providerTestSortOptionsByKey(): array {
    $data = [];

    // Test ascending key sort.
    $data[] = [
      [
        '30' => 'Value 30',
        '10' => 'Value 10',
        '20' => 'Value 20',
      ],
      'asc',
      [
        '10' => 'Value 10',
        '20' => 'Value 20',
        '30' => 'Value 30',
      ],
    ];

    // Test descending key sort.
    $data[] = [
      [
        '10' => 'Value 10',
        '30' => 'Value 30',
        '20' => 'Value 20',
      ],
      'desc',
      [
        '30' => 'Value 30',
        '20' => 'Value 20',
        '10' => 'Value 10',
      ],
    ];

    // Test with string keys.
    $data[] = [
      [
        'z' => 'Zebra',
        'a' => 'Apple',
        'm' => 'Monkey',
      ],
      'asc',
      [
        'a' => 'Apple',
        'm' => 'Monkey',
        'z' => 'Zebra',
      ],
    ];

    return $data;
  }

  /**
   * Tests the sortOptionsAlphabetical method.
   *
   * @dataProvider providerTestSortOptionsAlphabetical
   *
   * @covers ::sortOptionsAlphabetical
   *
   * @throws \PHPUnit\Framework\MockObject\Exception
   */
  public function testSortOptionsAlphabetical($unsorted, $direction, $natural, $expected) {
    // Data providers run before ::setUp. We rely on the stringTranslationTrait
    // for some of our option values so call it here instead.
    $this->stringTranslation = $this->getStringTranslationStub();

    $transliterator = $this->createMock(TransliterationInterface::class);
    $transliterator->expects($this->any())
      ->method('transliterate')
      ->willReturnCallback(
        fn($string, $langcode = 'en', $unknown_character = '?', $max_length = NULL) => str_replace([
          'á',
          'ã',
          'è',
          'ë',
          'ő',
        ], ['a', 'a', 'e', 'e', 'o'], $string)
      );
    $container = new ContainerBuilder();
    $container->set('transliteration', $transliterator);
    \Drupal::setContainer($container);

    $sorted = BetterExposedFiltersHelper::sortOptionsAlphabetical($unsorted, $direction, $natural);
    $this->assertEquals(array_values($expected), array_values($sorted));
  }

  /**
   * Data provider for ::testSortOptionsAlphabetical.
   */
  public static function providerTestSortOptionsAlphabetical(): array {
    $data = [];

    // Test natural sorting with numbers.
    $data[] = [
      [
        'item10' => 'Item 10',
        'item2' => 'Item 2',
        'item1' => 'Item 1',
        'item20' => 'Item 20',
      ],
      'asc',
      TRUE,
      [
        'item1' => 'Item 1',
        'item2' => 'Item 2',
        'item10' => 'Item 10',
        'item20' => 'Item 20',
      ],
    ];

    // Test regular sorting with numbers (lexicographic).
    $data[] = [
      [
        'item10' => 'Item 10',
        'item2' => 'Item 2',
        'item1' => 'Item 1',
        'item20' => 'Item 20',
      ],
      'asc',
      FALSE,
      [
        'item1' => 'Item 1',
        'item10' => 'Item 10',
        'item2' => 'Item 2',
        'item20' => 'Item 20',
      ],
    ];

    // Test descending order.
    $data[] = [
      [
        'a' => 'Apple',
        'c' => 'Cherry',
        'b' => 'Banana',
      ],
      'desc',
      TRUE,
      [
        'c' => 'Cherry',
        'b' => 'Banana',
        'a' => 'Apple',
      ],
    ];

    // Test with mixed content and natural sorting.
    $data[] = [
      [
        'mix' => 'File10.txt',
        'foo' => 'File2.txt',
        'bar' => 'File1.txt',
        'baz' => 'File20.txt',
      ],
      'asc',
      TRUE,
      [
        'bar' => 'File1.txt',
        'foo' => 'File2.txt',
        'mix' => 'File10.txt',
        'baz' => 'File20.txt',
      ],
    ];

    return $data;
  }

  /**
   * Tests edge cases for custom sorting methods.
   *
   * @throws \PHPUnit\Framework\MockObject\Exception
   */
  public function testSortOptionsCustomEdgeCases() {
    // Test empty array.
    $result = BetterExposedFiltersHelper::sortOptionsCustom([]);
    $this->assertEmpty($result, 'Empty array should remain empty');

    // Test single element.
    $single = ['key' => 'value'];
    $result = BetterExposedFiltersHelper::sortOptionsCustom($single);
    $this->assertEquals($single, $result, 'Single element array should remain unchanged');

    // Test invalid method falls back to default.
    $options = ['b' => 'Banana', 'a' => 'Apple'];
    $this->stringTranslation = $this->getStringTranslationStub();

    $transliterator = $this->createMock(TransliterationInterface::class);
    $transliterator->expects($this->any())
      ->method('transliterate')
      ->willReturnCallback(fn($string) => $string);
    $container = new ContainerBuilder();
    $container->set('transliteration', $transliterator);
    \Drupal::setContainer($container);

    $result = BetterExposedFiltersHelper::sortOptionsCustom($options, 'invalid_method');
    $expected = BetterExposedFiltersHelper::sortOptions($options);
    $this->assertEquals($expected, $result, 'Invalid method should fall back to default sorting');
  }

  /**
   * Tests options are rewritten correctly.
   *
   * @dataProvider providerTestSortNestedOptions
   *
   * @covers ::sortNestedOptions
   *
   * @throws \PHPUnit\Framework\MockObject\Exception
   */
  public function testSortNestedOptions($unsorted, $expected) {
    // Data providers run before ::setUp. We rely on the stringTranslationTrait
    // for some of our option values so call it here instead.
    $this->stringTranslation = $this->getStringTranslationStub();

    $transliterator = $this->createMock(TransliterationInterface::class);
    $transliterator->expects($this->any())
      ->method('transliterate')
      ->willReturnCallback(
        fn($string, $langcode = 'en', $unknown_character = '?', $max_length = NULL) => str_replace([
          'á',
          'é',
          'è',
        ], ['a', 'e', 'e'], $string)
      );
    $container = new ContainerBuilder();
    $container->set('transliteration', $transliterator);
    \Drupal::setContainer($container);

    $sorted = BetterExposedFiltersHelper::sortNestedOptions($unsorted);
    $this->assertEquals(array_values($sorted), array_values($expected));
  }

  /**
   * Data provider for ::testSortNestedOptions.
   */
  public static function providerTestSortNestedOptions(): array {
    $data = [];

    // List of nested taxonomy terms.
    $data[] = [
      [
        (object) ['option' => [2303 => 'United States']],
        (object) ['option' => [2311 => '-Washington']],
        (object) ['option' => [2312 => '--Seattle']],
        (object) ['option' => [2313 => '--Spokane']],
        (object) ['option' => [2314 => '--Walla Walla']],
        (object) ['option' => [2304 => '-California']],
        (object) ['option' => [2307 => '--Santa Barbara']],
        (object) ['option' => [2306 => '--San Diego']],
        (object) ['option' => [2305 => '--San Francisco']],
        (object) ['option' => [2308 => '-Oregon']],
        (object) ['option' => [2310 => '--Eugene']],
        (object) ['option' => [2309 => '--Portland']],
        (object) ['option' => [2324 => 'Mexico']],
        (object) ['option' => [2315 => 'Canada']],
        (object) ['option' => [2316 => '-British Columbia']],
        (object) ['option' => [2319 => '--Whistler']],
        (object) ['option' => [2317 => '--Vancouver']],
        (object) ['option' => [2318 => '--Victoria']],
        (object) ['option' => [2320 => '-Alberta']],
        (object) ['option' => [2321 => '--Calgary']],
        (object) ['option' => [2323 => '--Lake Louise']],
        (object) ['option' => [2322 => '--Edmonton']],
        (object) ['option' => [2315 => 'Spain']],
        (object) ['option' => [2311 => '-Cancun']],
        (object) ['option' => [2312 => '--Olot']],
        (object) ['option' => [2313 => '--Olèrdola']],
        (object) ['option' => [2314 => '--Barcelona']],
        (object) ['option' => [2304 => '-Alpha']],
        (object) ['option' => [2307 => '--Valdiciego']],
        (object) ['option' => [2307 => '--Valdés']],
        (object) ['option' => [2307 => '--Uviéu']],
      ], [
        (object) ['option' => [2315 => 'Canada']],
        (object) ['option' => [2320 => '-Alberta']],
        (object) ['option' => [2321 => '--Calgary']],
        (object) ['option' => [2322 => '--Edmonton']],
        (object) ['option' => [2323 => '--Lake Louise']],
        (object) ['option' => [2316 => '-British Columbia']],
        (object) ['option' => [2317 => '--Vancouver']],
        (object) ['option' => [2318 => '--Victoria']],
        (object) ['option' => [2319 => '--Whistler']],
        (object) ['option' => [2324 => 'Mexico']],
        (object) ['option' => [2315 => 'Spain']],
        (object) ['option' => [2304 => '-Alpha']],
        (object) ['option' => [2307 => '--Uviéu']],
        (object) ['option' => [2307 => '--Valdés']],
        (object) ['option' => [2307 => '--Valdiciego']],
        (object) ['option' => [2311 => '-Cancun']],
        (object) ['option' => [2314 => '--Barcelona']],
        (object) ['option' => [2313 => '--Olèrdola']],
        (object) ['option' => [2312 => '--Olot']],
        (object) ['option' => [2303 => 'United States']],
        (object) ['option' => [2304 => '-California']],
        (object) ['option' => [2306 => '--San Diego']],
        (object) ['option' => [2305 => '--San Francisco']],
        (object) ['option' => [2307 => '--Santa Barbara']],
        (object) ['option' => [2308 => '-Oregon']],
        (object) ['option' => [2310 => '--Eugene']],
        (object) ['option' => [2309 => '--Portland']],
        (object) ['option' => [2311 => '-Washington']],
        (object) ['option' => [2312 => '--Seattle']],
        (object) ['option' => [2313 => '--Spokane']],
        (object) ['option' => [2314 => '--Walla Walla']],
      ],
    ];

    return $data;
  }

}
