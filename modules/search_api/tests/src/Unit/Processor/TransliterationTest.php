<?php

namespace Drupal\Tests\search_api\Unit\Processor;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Plugin\search_api\processor\Transliteration;
use Drupal\search_api\Query\Query;
use Drupal\Tests\UnitTestCase;

// cspell:ignore translit

/**
 * Tests the "Transliteration" processor.
 *
 * @group search_api
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\processor\Transliteration
 */
class TransliterationTest extends UnitTestCase {

  use ProcessorTestTrait;
  use TestItemsTrait;

  /**
   * A test index mock to use for tests.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->index = new Index([], 'search_api_index');

    $this->setUpMockContainer();
    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager->method('getDefaultLanguage')->willReturn(new Language(['id' => 'en']));
    $language_manager->method('getCurrentLanguage')->willReturnMap([
      [LanguageInterface::TYPE_CONTENT, new Language(['id' => 'it'])],
    ]);
    \Drupal::getContainer()->set('language_manager', $language_manager);

    $this->processor = new Transliteration([], 'transliteration', []);
    $this->processor->setIndex($this->index);

    $transliterator = $this->createMock(TransliterationInterface::class);
    $transliterate = function ($string, $langcode = 'en', $unknown_character = '?', $max_length = NULL) {
      return "translit-$string-$langcode$unknown_character$max_length";
    };
    $transliterator->expects($this->any())
      ->method('transliterate')
      ->willReturnCallback($transliterate);
    /** @var \Drupal\Component\Transliteration\TransliterationInterface $transliterator */
    $this->processor->setTransliterator($transliterator);
  }

  /**
   * Tests that integers are not affected.
   *
   * @covers ::preprocessIndexItems
   * @covers ::process
   */
  public function testTransliterationWithInteger() {
    $field_value = 5;
    $items = $this->createSingleFieldItemWithLanguage('int', $field_value, $field);
    $this->processor->preprocessIndexItems($items);
    $this->assertEquals([$field_value], $field->getValues(), 'Integer not affected by transliteration.');
  }

  /**
   * Tests that floating point numbers are not affected.
   *
   * @covers ::preprocessIndexItems
   * @covers ::process
   */
  public function testTransliterationWithDouble() {
    $field_value = 3.14;
    $items = $this->createSingleFieldItemWithLanguage('double', $field_value, $field);
    $this->processor->preprocessIndexItems($items);
    $this->assertEquals([$field_value], $field->getValues(), 'Floating point number not affected by transliteration.');
  }

  /**
   * Tests that strings are affected.
   *
   * @covers ::preprocessIndexItems
   * @covers ::process
   */
  public function testTransliterationWithString() {
    $field_value = 'test_string';
    $items = $this->createSingleFieldItemWithLanguage('string', $field_value, $field);
    $this->processor->preprocessIndexItems($items);
    $expected_value = "translit-$field_value-en?";
    $this->assertEquals([$expected_value], $field->getValues(), 'Strings are correctly transliterated.');
  }

  /**
   * Tests that items with multiple languages are handled correctly.
   *
   * @covers ::preprocessIndexItems
   * @covers ::process
   */
  public function testLanguageHandling(): void {
    $items = [];
    $items += $this->createSingleFieldItemWithLanguage('string', 'foo', $field1, 'en');
    $items += $this->createSingleFieldItemWithLanguage('string', 'bar', $field2, 'de');
    $items += $this->createSingleFieldItemWithLanguage('string', 'foo', $field3, 'de');
    $items += $this->createSingleFieldItemWithLanguage('string', 'baz', $field4, 'fr');

    $this->processor->preprocessIndexItems($items);

    $this->assertEquals(['translit-foo-en?'], $field1->getValues());
    $this->assertEquals(['translit-bar-de?'], $field2->getValues());
    $this->assertEquals(['translit-foo-de?'], $field3->getValues());
    $this->assertEquals(['translit-baz-fr?'], $field4->getValues());
  }

  /**
   * Tests that queries are correctly processed.
   *
   * @covers ::preprocessSearchQuery
   * @covers ::process
   */
  public function testPreprocessQuery(): void {
    $query = Query::create($this->index)
      ->keys([
        '#conjunction' => 'AND',
        'foo',
      ])
      ->setLanguages(['fr']);
    $this->processor->preprocessSearchQuery($query);
    $this->assertEquals([
      '#conjunction' => 'AND',
      'translit-foo-fr?',
    ], $query->getKeys());

    $query = Query::create($this->index)
      ->keys([
        '#conjunction' => 'AND',
        'foo',
      ])
      ->setLanguages(['de']);
    $this->processor->preprocessSearchQuery($query);
    $this->assertEquals([
      '#conjunction' => 'AND',
      'translit-foo-de?',
    ], $query->getKeys());

    $query = Query::create($this->index)
      ->keys([
        '#conjunction' => 'AND',
        'bar',
      ])
      ->setLanguages(['es', 'pt']);
    $this->processor->preprocessSearchQuery($query);
    $this->assertEquals([
      '#conjunction' => 'AND',
      'translit-bar-it?',
    ], $query->getKeys());
  }

  /**
   * Creates an array with a single item which has the given field and language.
   *
   * @param string $field_type
   *   The field type to set for the field.
   * @param mixed $field_value
   *   A field value to add to the field.
   * @param \Drupal\search_api\Item\FieldInterface|null $field
   *   (optional) A variable, passed by reference, into which the created field
   *   will be saved.
   * @param string $langcode
   *   (optional) The language code to set on the item.
   *
   * @return \Drupal\search_api\Item\ItemInterface[]
   *   An array containing a single item with the specified field.
   */
  public function createSingleFieldItemWithLanguage(
    string $field_type,
    mixed $field_value,
    ?FieldInterface &$field = NULL,
    string $langcode = 'en',
  ): array {
    $items = $this->createSingleFieldItem($this->index, $field_type, $field_value, $field);
    reset($items)->setLanguage($langcode);
    return $items;
  }

}
