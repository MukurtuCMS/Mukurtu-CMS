<?php

namespace Drupal\Tests\search_api\Unit\Processor;

use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValue;
use Drupal\search_api\Plugin\search_api\processor\Stemmer;
use Drupal\search_api\Query\Condition;
use Drupal\search_api\Query\QueryInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "Stemmer" processor.
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\processor\Stemmer
 *
 * @group search_api
 */
class StemmerTest extends UnitTestCase {

  use ProcessorTestTrait;
  use TestItemsTrait;

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpMockContainer();

    $this->processor = new Stemmer([], 'string', []);
    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager->method('getLanguages')->willReturn([
      'de' => 'de',
      'en' => 'en',
      'en-GB' => 'en-GB',
      'fr' => 'fr',
      'it' => 'it',
    ]);
    $this->processor->setLanguageManager($language_manager);
  }

  /**
   * Tests the supportsIndex() method.
   *
   * @covers ::supportsIndex
   */
  public function testSupportsIndex() {
    $index = $this->createMock(IndexInterface::class);

    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $this->container->set('language_manager', $language_manager);
    $language_manager->method('getLanguages')->willReturn([
      new Language(['id' => LanguageInterface::LANGCODE_NOT_SPECIFIED]),
    ]);
    $this->assertFalse(Stemmer::supportsIndex($index));

    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $this->container->set('language_manager', $language_manager);
    $language_manager->method('getLanguages')->willReturn([
      new Language(['id' => 'de']),
      new Language(['id' => LanguageInterface::LANGCODE_NOT_SPECIFIED]),
      new Language(['id' => 'fr']),
    ]);
    $this->assertFalse(Stemmer::supportsIndex($index));

    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $this->container->set('language_manager', $language_manager);
    $language_manager->method('getLanguages')->willReturn([
      new Language(['id' => 'en']),
      new Language(['id' => 'fr']),
      new Language(['id' => LanguageInterface::LANGCODE_NOT_SPECIFIED]),
    ]);
    $this->assertTrue(Stemmer::supportsIndex($index));

    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $this->container->set('language_manager', $language_manager);
    $language_manager->method('getLanguages')->willReturn([
      new Language(['id' => 'fr']),
      new Language(['id' => LanguageInterface::LANGCODE_NOT_SPECIFIED]),
      new Language(['id' => 'en-GB']),
    ]);
    $this->assertTrue(Stemmer::supportsIndex($index));
  }

  /**
   * Tests the preprocessIndexItems() method.
   *
   * @covers ::preprocessIndexItems
   */
  public function testPreprocessIndexItems() {
    $index = $this->createMock(IndexInterface::class);

    $item_en = $this->getMockBuilder(ItemInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $item_en->method('getLanguage')->willReturn('en');
    $field_en = new Field($index, 'foo');
    $field_en->setType('text');
    $field_en->setValues([
      new TextValue('ties'),
    ]);
    $item_en->method('getFields')->willReturn(['foo' => $field_en]);

    $item_en_gb = $this->getMockBuilder(ItemInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $item_en_gb->method('getLanguage')->willReturn('en-GB');
    $field_en_gb = new Field($index, 'foo');
    $field_en_gb->setType('text');
    $field_en_gb->setValues([
      new TextValue('ties'),
    ]);
    $item_en_gb->method('getFields')->willReturn(['foo' => $field_en_gb]);

    $item_de = $this->getMockBuilder(ItemInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $item_de->method('getLanguage')->willReturn('de');
    $field_de = new Field($index, 'foo');
    $field_de->setType('text');
    $field_de->setValues([
      new TextValue('ties'),
    ]);
    $item_de->method('getFields')->willReturn(['foo' => $field_de]);

    $items = [$item_en, $item_en_gb, $item_de];
    $this->processor->preprocessIndexItems($items);

    /** @var \Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface $value */
    $value = $field_en->getValues()[0];
    $this->assertEquals('tie', $value->toText());
    $value = $field_en_gb->getValues()[0];
    $this->assertEquals('tie', $value->toText());
    $value = $field_de->getValues()[0];
    $this->assertEquals('ties', $value->toText());
  }

  /**
   * Tests the preprocessSearchQuery() method.
   *
   * @param string[]|null $languages
   *   The languages to set for the query.
   * @param bool $should_process
   *   Whether keys are expected to be processed by the processor or not.
   *
   * @covers ::preprocessSearchQuery
   *
   * @dataProvider preprocessSearchQueryDataProvider
   */
  public function testPreprocessSearchQuery(?array $languages, bool $should_process): void {
    /** @var \Drupal\search_api\Query\QueryInterface|\PHPUnit\Framework\MockObject\MockObject $query */
    $query = $this->createMock(QueryInterface::class);
    $query->method('getLanguages')->willReturn($languages);
    // Unfortunately, returning a reference (as getKeys() has to do for
    // processing to work) doesn't seem to be possible with a mock object. But
    // since the only code we really want to test is the language check, using
    // an exception works just as well, and is quite simple.
    $query->method('getKeys')->willThrowException(new \RuntimeException());

    try {
      $this->processor->preprocessSearchQuery($query);
      $this->assertFalse($should_process, "Keys weren't processed but should have been.");
    }
    catch (\RuntimeException) {
      $this->assertTrue($should_process, "Keys were processed but shouldn't have been.");
    }
  }

  /**
   * Provides sets of arguments for testPreprocessSearchQuery().
   *
   * @return array[]
   *   Arrays of arguments for testPreprocessSearchQuery().
   */
  public static function preprocessSearchQueryDataProvider(): array {
    return [
      'language-less query' => [NULL, TRUE],
      'English query' => [['en'], TRUE],
      'British English query' => [['en-GB'], TRUE],
      'Non-English query' => [['de'], FALSE],
      'Multilingual query (including English)' => [['en', 'fr', 'es'], TRUE],
      'Multilingual query (including British English)' => [['en-GB', 'fr', 'es'], TRUE],
      'Multilingual query (not including English)' => [['de', 'it'], FALSE],
    ];
  }

  /**
   * Tests the process() method.
   *
   * @param string $passed_value
   *   The value that should be passed into process().
   * @param string $expected_value
   *   The expected processed value.
   *
   * @covers ::process
   *
   * @dataProvider processDataProvider
   */
  public function testProcess(string $passed_value, string $expected_value) {
    $this->invokeMethod('process', [&$passed_value]);
    $this->assertEquals($passed_value, $expected_value);
  }

  /**
   * Provides sets of arguments for testProcess().
   *
   * @return array[]
   *   Arrays of arguments for testProcess().
   */
  public static function processDataProvider(): array {
    // cspell:disable
    return [
      ['Yo', 'yo'],
      ['ties', 'tie'],
      ['cries', 'cri'],
      ['exceed', 'exceed'],
      ['consign', 'consign'],
      ['consigned', 'consign'],
      ['consigning', 'consign'],
      ['consignment', 'consign'],
      ['consist', 'consist'],
      ['consisted', 'consist'],
      ['consistency', 'consist'],
      ['consistent', 'consist'],
      ['consistently', 'consist'],
      ['consisting', 'consist'],
      ['consists', 'consist'],
      ['consolation', 'consol'],
      ['consolations', 'consol'],
      ['consolatory', 'consolatori'],
      ['console', 'consol'],
      ['consoled', 'consol'],
      ['consoles', 'consol'],
      ['consolidate', 'consolid'],
      ['consolidated', 'consolid'],
      ['consolidating', 'consolid'],
      ['consoling', 'consol'],
      ['consolingly', 'consol'],
      ['consols', 'consol'],
      ['consonant', 'conson'],
      ['consort', 'consort'],
      ['consorted', 'consort'],
      ['consorting', 'consort'],
      ['conspicuous', 'conspicu'],
      ['conspicuously', 'conspicu'],
      ['conspiracy', 'conspiraci'],
      ['conspirator', 'conspir'],
      ['conspirators', 'conspir'],
      ['conspire', 'conspir'],
      ['conspired', 'conspir'],
      ['conspiring', 'conspir'],
      ['constable', 'constabl'],
      ['constables', 'constabl'],
      ['constance', 'constanc'],
      ['constancy', 'constanc'],
      ['constant', 'constant'],
      ['knack', 'knack'],
      ['knackeries', 'knackeri'],
      ['knacks', 'knack'],
      ['knag', 'knag'],
      ['knave', 'knave'],
      ['knaves', 'knave'],
      ['knavish', 'knavish'],
      ['kneaded', 'knead'],
      ['kneading', 'knead'],
      ['knee', 'knee'],
      ['kneel', 'kneel'],
      ['kneeled', 'kneel'],
      ['kneeling', 'kneel'],
      ['kneels', 'kneel'],
      ['knees', 'knee'],
      ['knell', 'knell'],
      ['knelt', 'knelt'],
      ['knew', 'knew'],
      ['knick', 'knick'],
      ['knif', 'knif'],
      ['knife', 'knife'],
      ['knight', 'knight'],
      ['knightly', 'knight'],
      ['knights', 'knight'],
      ['knit', 'knit'],
      ['knits', 'knit'],
      ['knitted', 'knit'],
      ['knitting', 'knit'],
      ['knives', 'knive'],
      ['knob', 'knob'],
      ['knobs', 'knob'],
      ['knock', 'knock'],
      ['knocked', 'knock'],
      ['knocker', 'knocker'],
      ['knockers', 'knocker'],
      ['knocking', 'knock'],
      ['knocks', 'knock'],
      ['knopp', 'knopp'],
      ['knot', 'knot'],
      ['knots', 'knot'],
      // This can happen when Tokenizer is off during indexing, or when
      // preprocessing a search query with quoted keywords.
      [" \tExtra  spaces \rappeared \n", 'extra space appear'],
      ["\tspaced-out  \r\n", 'space out'],
    ];
    // cspell:enable
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

}
