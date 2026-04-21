<?php

namespace Drupal\Tests\search_api\Unit\Processor;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\Field\TypedData\FieldItemDataDefinition;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\search_api\processor\Highlight;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSet;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests the "Highlight" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\Highlight
 */
class HighlightTest extends UnitTestCase {

  use TestItemsTrait;

  /**
   * The processor to be tested.
   *
   * @var \Drupal\search_api\Plugin\search_api\processor\Highlight
   */
  protected $processor;

  /**
   * The index mock used for the tests.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpMockContainer();

    $this->processor = new Highlight([], 'highlight', []);

    $this->index = $this->createMock(IndexInterface::class);
    $this->index->method('getFulltextFields')
      ->willReturn(['body', 'title']);
    $property_definitions = [
      'body' => new FieldConfig([
        'field_name' => 'body',
        'entity_type' => 'node',
        'bundle' => 'article',
        'itemDefinition' => new FieldItemDataDefinition(),
      ]),
      'title' => new FieldConfig([
        'field_name' => 'title',
        'entity_type' => 'node',
        'bundle' => 'article',
        'itemDefinition' => new FieldItemDataDefinition(),
      ]),
    ];
    $this->index->method('getPropertyDefinitions')
      ->willReturn($property_definitions);
    $this->processor->setIndex($this->index);

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $this->getStringTranslationStub();
    $this->processor->setStringTranslation($translation);
  }

  /**
   * Tests whether the processor handles field ID changes correctly.
   */
  public function testFieldRenaming() {
    $configuration['exclude_fields'] = ['body', 'title'];
    $this->processor->setConfiguration($configuration);

    $this->index->method('getFieldRenames')
      ->willReturn([
        'title' => 'foobar',
      ]);

    $this->processor->preIndexSave();

    $fields = $this->processor->getConfiguration()['exclude_fields'];
    sort($fields);
    $this->assertEquals(['body', 'foobar'], $fields);
  }

  /**
   * Tests postprocessing with an empty result set.
   */
  public function testPostprocessSearchResultsWithEmptyResult() {
    $query = $this->createMock(QueryInterface::class);

    $results = $this->getMockBuilder(ResultSet::class)
      ->onlyMethods(['getResultCount', 'getQuery', 'getResultItems'])
      ->setConstructorArgs([$query])
      ->getMock();

    $results->expects($this->once())
      ->method('getResultCount')
      ->willReturn(0);
    $results->expects($this->once())
      ->method('getQuery')
      ->willReturn(NULL);
    $results->expects($this->never())
      ->method('getResultItems');
    /** @var \Drupal\search_api\Query\ResultSet $results */

    $this->processor->postprocessSearchResults($results);
  }

  /**
   * Makes sure that queries with "basic" processing set are ignored.
   */
  public function testPostprocessBasicQuery() {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_BASIC);

    $results = $this->getMockBuilder(ResultSet::class)
      ->onlyMethods(['getResultCount', 'getQuery', 'getResultItems'])
      ->setConstructorArgs([$query])
      ->getMock();

    $results->expects($this->once())
      ->method('getResultCount')
      ->willReturn(1);
    $results->expects($this->once())
      ->method('getQuery')
      ->willReturn($query);
    $results->expects($this->never())
      ->method('getResultItems');
    /** @var \Drupal\search_api\Query\ResultSet $results */

    $this->processor->postprocessSearchResults($results);
  }

  /**
   * Tests postprocessing on a query without keywords.
   */
  public function testPostprocessSearchResultsWithoutKeywords() {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);

    $results = $this->getMockBuilder(ResultSet::class)
      ->onlyMethods(['getResultCount', 'getQuery', 'getResultItems'])
      ->setConstructorArgs([$query])
      ->getMock();

    $query->expects($this->once())
      ->method('getOriginalKeys')
      ->willReturn([]);

    $results->expects($this->once())
      ->method('getResultCount')
      ->willReturn(1);
    $results->expects($this->once())
      ->method('getQuery')
      ->willReturn($query);
    $results->expects($this->never())
      ->method('getResultItems');
    /** @var \Drupal\search_api\Query\ResultSet $results */

    $this->processor->postprocessSearchResults($results);
  }

  /**
   * Tests postprocessing on a query with the skip tag.
   */
  public function testPostprocessSearchResultsWithSkipTag() {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->once())
      ->method('hasTag')
      ->with('search_api_skip_processor_highlight')
      ->willReturn(TRUE);

    $results = $this->getMockBuilder(ResultSet::class)
      ->onlyMethods(['getResultCount', 'getQuery', 'getResultItems'])
      ->setConstructorArgs([$query])
      ->getMock();

    $results->expects($this->once())
      ->method('getResultCount')
      ->willReturn(1);
    $results->expects($this->once())
      ->method('getQuery')
      ->willReturn($query);
    $results->expects($this->never())
      ->method('getResultItems');
    /** @var \Drupal\search_api\Query\ResultSet $results */

    $this->processor->postprocessSearchResults($results);
  }

  /**
   * Tests field highlighting with a normal result set.
   */
  public function testPostprocessSearchResultsWithResults() {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn('foo');
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $field = $this->createTestField('body', 'entity:node/body');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $field]);

    $body_values = ['Some foo value'];
    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => $body_values,
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $fields = $items[$this->itemIds[0]]->getExtraData('highlighted_fields');
    $this->assertEquals('Some <strong>foo</strong> value', $fields['body'][0], 'Highlighting is correctly applied to body field.');
  }

  /**
   * Tests empty search keys do not create empty highlight tags.
   */
  public function testPostprocessSearchResultsWithEmptyKeys(): void {
    $this->processor->setConfiguration(['excerpt_always' => TRUE]);

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn('');
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $field = $this->createTestField('body', 'entity:node/body');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $field]);

    $this->processor->setIndex($this->index);

    $body_values = ['Some foo value'];
    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => $body_values,
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $fields = $items[$this->itemIds[0]]->getExtraData('highlighted_fields');
    // There should be no highlighted fields when keys are empty.
    $this->assertNull($fields, 'Highlighted fields are not empty when keys are empty');
  }

  /**
   * Tests changing the prefix and suffix used for highlighting.
   */
  public function testPostprocessSearchResultsWithChangedPrefixSuffix() {
    $this->processor->setConfiguration([
      'prefix' => '<em>',
      'suffix' => '</em>',
    ]);

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn(['#conjunction' => 'AND', 'foo']);
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $field = $this->createTestField('body', 'entity:node/body');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $field]);

    $body_values = ['Some foo value'];
    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => $body_values,
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $fields = $items[$this->itemIds[0]]->getExtraData('highlighted_fields');
    $this->assertEquals('Some <em>foo</em> value', $fields['body'][0], 'Highlighting is correctly applied');
  }

  /**
   * Tests whether field highlighting can be disabled.
   */
  public function testPostprocessSearchResultsWithoutHighlight() {
    $this->processor->setConfiguration(['highlight' => 'never']);

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn(['#conjunction' => 'AND', 'foo']);
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $field = $this->createTestField('body', 'entity:node/body');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $field]);

    $body_values = ['Some foo value'];
    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => $body_values,
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $fields = $items[$this->itemIds[0]]->getExtraData('highlighted_fields');
    $this->assertEmpty($fields, 'Highlighting is not applied when disabled.');
  }

  /**
   * Tests highlighting of partial matches.
   *
   * @param string $text
   *   The text that should be highlighted.
   * @param string $keywords
   *   The search keywords.
   * @param string $highlighted
   *   The expected highlighted text.
   *
   * @dataProvider postprocessSearchResultsHighlightPartialDataProvider
   */
  public function testPostprocessSearchResultsHighlightPartial($text, $keywords, $highlighted) {
    $this->processor->setConfiguration(['highlight_partial' => TRUE]);

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn(['#conjunction' => 'AND', $keywords]);
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $field = $this->createTestField('body', 'entity:node/body');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $field]);

    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => [$text],
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $fields = $items[$this->itemIds[0]]->getExtraData('highlighted_fields');
    $this->assertEquals($highlighted, $fields['body'][0], 'Highlighting is correctly applied to a partial match.');
    $excerpt = $items[$this->itemIds[0]]->getExcerpt();
    $this->assertEquals("… $highlighted …", $excerpt, 'Highlighting is correctly applied to a partial match.');
  }

  /**
   * Provides test data sets for testPostprocessSearchResultsHighlightPartial().
   *
   * @return array[]
   *   An array of argument arrays for
   *   testPostprocessSearchResultsHighlightPartial().
   *
   * @see \Drupal\Tests\search_api\Unit\Processor\HighlightTest::testPostprocessSearchResultsHighlightPartial()
   */
  public static function postprocessSearchResultsHighlightPartialDataProvider() {
    // cspell:disable
    return [
      'normal' => [
        'Some longwordtoshowpartialmatching value',
        'partial',
        'Some longwordtoshow<strong>partial</strong>matching value',
      ],
      'multi-byte' => [
        'Alle Angaben ohne Gewähr.',
        'Ähr',
        'Alle Angaben ohne Gew<strong>ähr</strong>.',
      ],
    ];
    // cspell:enable
  }

  /**
   * Tests field highlighting when previous highlighting is present.
   */
  public function testPostprocessSearchResultsWithPreviousHighlighting() {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn(['#conjunction' => 'AND', 'foo']);
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $field = $this->createTestField('body', 'entity:node/body');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $field]);

    $body_values = ['Some foo value'];
    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => $body_values,
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);
    $highlighted_fields["body_2"][0] = 'Old highlighting text';
    $highlighted_fields["body_2"][1] = 'More highlighting text';
    $items[$this->itemIds[0]]->setExtraData('highlighted_fields', $highlighted_fields);

    $this->processor->postprocessSearchResults($results);

    $fields = $items[$this->itemIds[0]]->getExtraData('highlighted_fields');
    $this->assertEquals('Some <strong>foo</strong> value', $fields['body'][0], 'Highlighting correctly applied to body field.');
    $this->assertEquals('Old highlighting text', $fields["body_2"][0], 'Old highlighting data is preserved.');
    $this->assertEquals('More highlighting text', $fields["body_2"][1], 'Old highlighting data is preserved.');
  }

  /**
   * Tests whether highlighting works on a longer text.
   */
  public function testPostprocessSearchResultsExcerpt() {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn(['#conjunction' => 'AND', 'congue']);
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $field = $this->createTestField('body', 'entity:node/body');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $field]);

    $body_values = [$this->getFieldBody()];
    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => $body_values,
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $output = $results->getResultItems();
    $excerpt = $output[$this->itemIds[0]]->getExcerpt();
    $correct_output = '… tristique, ligula sit amet condimentum dapibus, lorem nunc <strong>congue</strong> velit, et dictum augue leo sodales augue. Maecenas …';
    $this->assertEquals($correct_output, $excerpt, 'Excerpt was added.');
  }

  /**
   * Tests whether excerpt creation correctly handles HTML tags.
   */
  public function testPostprocessSearchResultsExcerptStripTags() {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn(['#conjunction' => 'AND', 'foo']);
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $field = $this->createTestField('body', 'entity:node/body');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $field]);

    $body_value = <<<'END'
Sentence with foo keyword.
<script>
var foo = 1;
</script>
<style>
a.foo {
  font-weight: bold;
}
</style>
And another foo!
END;
    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => [$body_value],
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $output = $results->getResultItems();
    $excerpt = $output[$this->itemIds[0]]->getExcerpt();
    $correct_output = '… Sentence with <strong>foo</strong> keyword. And another <strong>foo</strong>! …';
    $this->assertEquals($correct_output, $excerpt, 'Excerpt was added.');
  }

  /**
   * Tests whether fields highlighting correctly handles invalid HTML.
   *
   * @see https://www.drupal.org/node/3264239
   */
  public function testPostprocessSearchResultsInvalidHtml() {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->method('warning')->willReturnCallback(function (string $message, array $context = []) {
      $context_json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
      trigger_error("$message\n$context_json", E_USER_WARNING);
    });
    $this->processor->setLogger($logger);

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn(['#conjunction' => 'AND', 'foo']);
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $field = $this->createTestField('body', 'entity:node/body');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $field]);

    $this->processor->setIndex($this->index);

    $body_value = <<<'END'
Sentence with foo keyword.
<img src="" width="" alt="" "></img>
More words.
END;
    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => [$body_value],
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $expected = <<<'END'
Sentence with <strong>foo</strong> keyword.
<img src="" width="" alt="" "></img>
More words.
END;
    $highlighted = reset($items)->getExtraData('highlighted_fields');
    $this->assertEquals([$expected], $highlighted['body'] ?? NULL);
  }

  /**
   * Tests whether highlighting works on a longer text matching near the end.
   */
  public function testPostprocessSearchResultsExcerptMatchNearEnd() {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn(['#conjunction' => 'AND', 'diam']);
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $field = $this->createTestField('body', 'entity:node/body');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $field]);

    $body_values = [$this->getFieldBody()];
    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => $body_values,
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $output = $results->getResultItems();
    $excerpt = $output[$this->itemIds[0]]->getExcerpt();
    $correct_output = '… Fusce in mauris eu leo fermentum feugiat. Proin varius <strong>diam</strong> ante, non eleifend ipsum luctus sed. …';
    $this->assertEquals($correct_output, $excerpt, 'Excerpt was added.');
  }

  /**
   * Tests whether highlighting works with a changed excerpt length.
   */
  public function testPostprocessSearchResultsWithChangedExcerptLength() {
    $this->processor->setConfiguration(['excerpt_length' => 64]);

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn('congue');
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $field = $this->createTestField('body', 'entity:node/body');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $field]);

    $body_values = [$this->getFieldBody()];
    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => $body_values,
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $output = $results->getResultItems();
    $excerpt = $output[$this->itemIds[0]]->getExcerpt();
    $correct_output = '… dapibus, lorem nunc <strong>congue</strong> velit, et dictum augue …';
    $this->assertEquals($correct_output, $excerpt, 'Excerpt has correct reduced length.');
  }

  /**
   * Tests whether adding an excerpt can be successfully disabled.
   */
  public function testPostprocessSearchResultsWithoutExcerpt() {
    $this->processor->setConfiguration(['excerpt' => FALSE]);

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn(['#conjunction' => 'AND', 'congue']);
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $field = $this->createTestField('body', 'entity:node/body');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $field]);

    $body_values = [$this->getFieldBody()];
    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => $body_values,
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $excerpt = $items[$this->itemIds[0]]->getExcerpt();

    $this->assertEmpty($excerpt, 'No excerpt added when disabled.');
  }

  /**
   * Tests whether excerpt creation uses the "highlighted_keys" extra data.
   */
  public function testPostprocessSearchResultsExcerptWithKeysFromBackend() {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      // cspell:disable-next-line
      ->willReturn(['#conjunction' => 'AND', 'congues']);
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $field = $this->createTestField('body', 'entity:node/body');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $field]);

    $body_values = [$this->getFieldBody()];
    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => $body_values,
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);
    // cspell:disable-next-line
    $items[$this->itemIds[0]]->setExtraData('highlighted_keys', ['congue']);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $output = $results->getResultItems();
    $excerpt = $output[$this->itemIds[0]]->getExcerpt();
    // cspell:disable-next-line
    $correct_output = '… tristique, ligula sit amet condimentum dapibus, lorem nunc <strong>congue</strong> velit, et dictum augue leo sodales augue. Maecenas …';
    $this->assertEquals($correct_output, $excerpt, 'Excerpt was added.');
  }

  /**
   * Tests whether highlighting works on a longer text.
   */
  public function testPostprocessSearchResultsWithComplexKeys() {
    $keys = [
      '#conjunction' => 'AND',
      [
        '#conjunction' => 'OR',
        'foo',
        'bar',
      ],
      'baz',
      [
        '#conjunction' => 'OR',
        '#negation' => TRUE,
        'text',
        'will',
      ],
    ];
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn($keys);
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $body_field = $this->createTestField('body', 'entity:node/body');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $body_field]);

    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => [
          'This foo text bar will get baz riddled with &lt;strong&gt; tags.',
        ],
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $fields = $items[$this->itemIds[0]]->getExtraData('highlighted_fields');
    $this->assertEquals('This <strong>foo</strong> text <strong>bar</strong> will get <strong>baz</strong> riddled with &lt;strong&gt; tags.', $fields['body'][0], 'Highlighting is correctly applied when keys are complex.');
    $correct_output = '… This <strong>foo</strong> text <strong>bar</strong> will get <strong>baz</strong> riddled with &lt;strong&gt; tags. …';
    $excerpt = $items[$this->itemIds[0]]->getExcerpt();
    $this->assertEquals($correct_output, $excerpt, 'Excerpt was added.');
  }

  /**
   * Tests field highlighting and excerpts for two fields.
   */
  public function testPostprocessSearchResultsWithTwoFields() {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn(['#conjunction' => 'AND', 'foo']);
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $body_field = $this->createTestField('body', 'entity:node/body');
    $title_field = $this->createTestField('title', 'title');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn([
        'body' => $body_field,
        'title' => $title_field,
      ]);

    $body_values = ['Some foo value', 'foo bar'];
    $title_values = ['Title foo'];
    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => $body_values,
      ],
      'title' => [
        'type' => 'text',
        'values' => $title_values,
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $fields = $items[$this->itemIds[0]]->getExtraData('highlighted_fields');
    $this->assertEquals('Some <strong>foo</strong> value', $fields['body'][0], 'Highlighting is correctly applied to first body field value.');
    $this->assertEquals('<strong>foo</strong> bar', $fields['body'][1], 'Highlighting is correctly applied to second body field value.');
    $this->assertEquals('Title <strong>foo</strong>', $fields['title'][0], 'Highlighting is correctly applied to title field.');

    $excerpt = $items[$this->itemIds[0]]->getExcerpt();
    $this->assertStringContainsString('Some <strong>foo</strong> value', $excerpt);
    $this->assertStringContainsString('<strong>foo</strong> bar', $excerpt);
    $this->assertStringContainsString('Title <strong>foo</strong>', $excerpt);
    $this->assertEquals(4, substr_count($excerpt, '…'));
  }

  /**
   * Tests field highlighting and excerpts with two items.
   */
  public function testPostprocessSearchResultsWithTwoItems() {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn(['#conjunction' => 'OR', 'foo']);
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $body_field = $this->createTestField('body', 'entity:node/body');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $body_field]);

    $body_values = ['Some foo value', 'foo bar'];
    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => $body_values,
      ],
    ];

    $items = $this->createItems($this->index, 2, $fields);

    $items[$this->itemIds[1]]->getField('body')
      ->setValues(['The second item also contains foo in its body.']);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $fields = $items[$this->itemIds[0]]->getExtraData('highlighted_fields');
    $this->assertEquals('Some <strong>foo</strong> value', $fields['body'][0], 'Highlighting is correctly applied to first body field value.');
    $this->assertEquals('<strong>foo</strong> bar', $fields['body'][1], 'Highlighting is correctly applied to second body field value.');

    $fields = $items[$this->itemIds[1]]->getExtraData('highlighted_fields');
    $this->assertEquals('The second item also contains <strong>foo</strong> in its body.', $fields['body'][0], 'Highlighting is correctly applied to second item.');

    $excerpt1 = '… Some <strong>foo</strong> value … <strong>foo</strong> bar …';
    $excerpt2 = '… The second item also contains <strong>foo</strong> in its body. …';
    $this->assertEquals($excerpt1, $items[$this->itemIds[0]]->getExcerpt(), 'Correct excerpt created from two text fields.');
    $this->assertEquals($excerpt2, $items[$this->itemIds[1]]->getExcerpt(), 'Correct excerpt created for second item.');
  }

  /**
   * Tests excerpts with some fields excluded.
   */
  public function testExcerptExcludeFields() {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn(['#conjunction' => 'AND', 'foo']);
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $body_field = $this->createTestField('body', 'entity:node/body');
    $title_field = $this->createTestField('title', 'title');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn([
        'body' => $body_field,
        'title' => $title_field,
      ]);

    $this->processor->setConfiguration([
      'exclude_fields' => ['title'],
    ]);

    $body_values = ['Some foo value', 'foo bar'];
    $title_values = ['Title foo'];
    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => $body_values,
      ],
      'title' => [
        'type' => 'text',
        'values' => $title_values,
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $fields = $items[$this->itemIds[0]]->getExtraData('highlighted_fields');
    $this->assertEquals('Some <strong>foo</strong> value', $fields['body'][0], 'Highlighting is correctly applied to first body field value.');
    $this->assertEquals('<strong>foo</strong> bar', $fields['body'][1], 'Highlighting is correctly applied to second body field value.');
    $this->assertEquals('Title <strong>foo</strong>', $fields['title'][0], 'Highlighting is correctly applied to title field.');

    $excerpt = '… Some <strong>foo</strong> value … <strong>foo</strong> bar …';
    $this->assertEquals($excerpt, $items[$this->itemIds[0]]->getExcerpt(), 'Correct excerpt created ignoring title field.');
  }

  /**
   * Tests that field extraction in the processor works correctly.
   */
  public function testFieldExtraction() {
    /** @var \Drupal\Core\TypedData\ComplexDataInterface|\PHPUnit\Framework\MockObject\MockObject $object */
    $object = $this->createMock(ComplexDataInterface::class);
    $bar_foo_property = $this->createMock(TypedDataInterface::class);
    $bar_foo_property->method('getValue')
      ->willReturn('value3 foo');
    $bar_foo_property->method('getDataDefinition')
      ->willReturn(new DataDefinition());
    $bar_property = $this->createMock(ComplexDataInterface::class);
    $bar_property->method('get')
      ->willReturnMap([
        ['foo', $bar_foo_property],
      ]);
    $bar_property->method('getProperties')
      ->willReturn([
        'foo' => TRUE,
      ]);
    $foobar_property = $this->createMock(TypedDataInterface::class);
    $foobar_property->method('getValue')
      ->willReturn('wrong_value2 foo');
    $foobar_property->method('getDataDefinition')
      ->willReturn(new DataDefinition());
    $object->method('get')
      ->willReturnMap([
        ['bar', $bar_property],
        ['foobar', $foobar_property],
      ]);
    $object->method('getProperties')
      ->willReturn([
        'bar' => TRUE,
        'foobar' => TRUE,
      ]);

    $this->index = $this->createMock(IndexInterface::class);
    $this->processor->setIndex($this->index);
    $this->index->method('getFields')
      ->willReturn([
        'field1' => $this->createTestField('field1', 'entity:test1/bar:foo'),
        'field2' => $this->createTestField('field2', 'entity:test2/foobar'),
        'field3' => $this->createTestField('field3', 'foo'),
        'field4' => $this->createTestField('field4', 'baz', FALSE),
        'field5' => $this->createTestField('field5', 'entity:test1/foobar'),
      ]);
    $this->index->method('getPropertyDefinitions')
      ->willReturnMap([
        [
          NULL,
          [
            'foo' => new ProcessorProperty([
              'processor_id' => 'processor1',
            ]),
          ],
        ],
        [
          'entity:test1',
          [
            'bar' => $this->createFieldItemDataDefinition('text'),
            'foobar' => $this->createFieldItemDataDefinition('text'),
          ],
        ],
        [
          'entity:test2',
          [
            'foobar' => $this->createFieldItemDataDefinition('text'),
          ],
        ],
      ]);
    $processor_mock = $this->createMock(ProcessorInterface::class);
    $processor_mock->method('addFieldValues')
      ->willReturnCallback(function (ItemInterface $item) {
        foreach ($item->getFields(FALSE) as $field) {
          if ($field->getCombinedPropertyPath() == 'foo') {
            $field->setValues(['value4 foo', 'value5 foo']);
          }
        }
      });
    $this->index->method('getProcessorsByStage')
      ->willReturnMap([
        [
          ProcessorInterface::STAGE_ADD_PROPERTIES,
          [],
          [
            'aggregated_field' => $this->processor,
            'processor1' => $processor_mock,
          ],
        ],
      ]);
    $this->processor->setIndex($this->index);

    /** @var \Drupal\search_api\Datasource\DatasourceInterface|\PHPUnit\Framework\MockObject\MockObject $datasource */
    $datasource = $this->createMock(DatasourceInterface::class);
    $datasource->method('getPluginId')
      ->willReturn('entity:test1');

    $item = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createItem($this->index, 'id', $datasource);
    $item->setOriginalObject($object);
    $field = $this->createTestField('field4', 'baz')
      ->addValue('wrong_value1 foo');
    $item->setField('field4', $field);
    $field = $this->createTestField('field5', 'entity:test1/foobar')
      ->addValue('value1 foo')
      ->addValue('value2 foo');
    $item->setField('field5', $field);

    $this->processor->setConfiguration(['excerpt' => FALSE]);
    /** @var \Drupal\search_api\Query\QueryInterface|\PHPUnit\Framework\MockObject\MockObject $query */
    $query = $this->createMock(QueryInterface::class);
    $query->method('getOriginalKeys')
      ->willReturn('foo');
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $results = new ResultSet($query);
    $results->setResultCount(1)
      ->setResultItems([$item]);
    $this->processor->postprocessSearchResults($results);

    $expected = [
      'field1' => [
        'value3 <strong>foo</strong>',
      ],
      'field3' => [
        'value4 <strong>foo</strong>',
        'value5 <strong>foo</strong>',
      ],
      'field5' => [
        'value1 <strong>foo</strong>',
        'value2 <strong>foo</strong>',
      ],
    ];
    $fields = $item->getExtraData('highlighted_fields');
    $this->assertEquals($expected, $fields);
  }

  /**
   * Provides a regression test for bug #3022724.
   *
   * Before the bug was fixed, certain combinations of text field values and
   * keywords could lead to an infinite loop, or at least to the excerpt only
   * containing the first snippet.
   *
   * @param string $text
   *   The input text.
   * @param string[] $keys
   *   The keys that should be highlighted.
   * @param string $expected
   *   The expected result containing the highlighted strings.
   *
   * @see https://www.drupal.org/node/3022724
   *
   * @dataProvider regressionBug3022724DataProvider
   */
  public function testRegressionBug3022724($text, array $keys, $expected) {
    /* @see \Drupal\search_api\Plugin\search_api\processor\Highlight::createExcerptForFields() */
    $method = new \ReflectionMethod($this->processor, 'createExcerptForFields');
    $excerpt = $method->invoke(
      $this->processor,
      [
        [
          'field_id' => 'foo',
          'values' => [$text],
        ],
      ],
      $keys,
    );
    $this->assertEquals($expected, $excerpt);
  }

  /**
   * Provides test data for testRegressionBug3022724().
   *
   * @return array
   *   An array of argument arrays for testRegressionBug3022724().
   */
  public static function regressionBug3022724DataProvider() {
    // cspell:disable
    return [
      'multiple snippets' => [
        'text' => 'field value 1 … field value 2 … field value with first foo match … then long text of no matches, interspersed with some Hungarian: Jó napot kívánok! Hogy vagy? … then a field value with a second foo match – will it get highlighted?',
        'keys' => ['foo'],
        'expected' => '… field value 1 … field value 2 … field value with first <strong>foo</strong> match … then long text of no matches, interspersed with … kívánok! Hogy vagy? … then a field value with a second <strong>foo</strong> match – will it get highlighted? …',
      ],
      'overlong word in prefix' => [
        'text' => 'field value 1 … field value 2 … this is someVeryLongTextWhichHasNoSpacesInItWhichCanLeadToProblemsGettingAContextForHighlightingThis.Foo.Match',
        'keys' => ['foo'],
        'expected' => '… nItWhichCanLeadToProblemsGettingAContextForHighlightingThis.<strong>Foo</strong>.Match …',
      ],
    ];
    // cspell:enable
  }

  /**
   * Provides a regression test for bug #3390450.
   *
   * The effect of the bug was undesired highlighting of HTML attribute values.
   *
   * @see https://www.drupal.org/node/3390450
   */
  public function testRegressionBug3390450(): void {
    $method = new \ReflectionMethod($this->processor, 'highlightField');
    $text = '<h1 title="agreement">Main</h1><a href="/underwriting-agreement">investment underwriting agreement</a>';
    $excerpt = $method->invoke($this->processor, $text, ['agreement']);
    $expected = '<h1 title="agreement">Main</h1><a href="/underwriting-agreement">investment underwriting <strong>agreement</strong></a>';
    $this->assertEquals($expected, $excerpt);
  }

  /**
   * Tests field highlighting with a result set that has some highlighting data.
   */
  public function testPostprocessSearchResultsWithExistingHighlighting() {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn('foo');
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $body = $this->createTestField('body', 'entity:node/body');
    $title = $this->createTestField('title', 'entity:node/title');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $body, 'title' => $title]);

    $body_values = ['Some foo value'];
    $title_values = ['The foo title'];
    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => $body_values,
      ],
      'entity:node/title' => [
        'type' => 'text',
        'values' => $title_values,
      ],
    ];

    $items = $this->createItems($this->index, 2, $fields);
    $items[$this->itemIds[0]]->setExtraData('highlighted_fields', [
      'title' => ['The <em>foo</em> title'],
    ]);
    $items[$this->itemIds[1]]->setExtraData('highlighted_fields', [
      'body' => ['Some <em>foo</em> value'],
    ]);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $fields = $items[$this->itemIds[0]]->getExtraData('highlighted_fields');
    $this->assertEquals('The <em>foo</em> title', $fields['title'][0], 'Existing highlighting data for title was correctly kept.');
    $this->assertEquals('Some <strong>foo</strong> value', $fields['body'][0], 'Highlighting is correctly applied to body field.');
    $fields = $items[$this->itemIds[1]]->getExtraData('highlighted_fields');
    $this->assertEquals('The <strong>foo</strong> title', $fields['title'][0], 'Highlighting is correctly applied to title field.');
    $this->assertEquals('Some <em>foo</em> value', $fields['body'][0], 'Existing highlighting data for body was correctly kept.');
  }

  /**
   * Tests that the "excerpt_always" option works correctly.
   */
  public function testNoKeysExcerpt(): void {
    $this->processor->setConfiguration([
      'highlight' => 'never',
      'excerpt_always' => TRUE,
      'excerpt_length' => 45,
    ]);

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getProcessingLevel')
      ->willReturn(QueryInterface::PROCESSING_FULL);
    $query->expects($this->atLeastOnce())
      ->method('getOriginalKeys')
      ->willReturn(NULL);
    /** @var \Drupal\search_api\Query\QueryInterface $query */

    $field = $this->createTestField('body', 'entity:node/body');

    $this->index->expects($this->atLeastOnce())
      ->method('getFields')
      ->willReturn(['body' => $field]);

    $this->processor->setIndex($this->index);

    $fields = [
      'entity:node/body' => [
        'type' => 'text',
        'values' => [$this->getFieldBody()],
      ],
    ];

    $items = $this->createItems($this->index, 1, $fields);

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $fields = $items[$this->itemIds[0]]->getExtraData('highlighted_fields');
    $this->assertEmpty($fields);
    $excerpt = $items[$this->itemIds[0]]->getExcerpt();
    $expected = 'Lorem ipsum dolor sit amet, consectetur …';
    $this->assertEquals($expected, $excerpt, 'Highlighting is correctly applied to a partial match.');
  }

  /**
   * Creates a field object for testing.
   *
   * @param string $id
   *   The field ID to set.
   * @param string $combined_property_path
   *   The combined property path of the field.
   * @param bool $text
   *   (optional) Whether the field should be a fulltext field or not.
   *
   * @return \Drupal\search_api\Item\FieldInterface
   *   A field object.
   */
  protected function createTestField($id, $combined_property_path, $text = TRUE) {
    $field = new Field($this->index, $id);
    [$datasource_id, $property_path] = Utility::splitCombinedId($combined_property_path);
    $field->setDatasourceId($datasource_id);
    $field->setPropertyPath($property_path);
    $field->setType($text ? 'text' : 'string');

    return $field;
  }

  /**
   * Returns a long text to use for highlighting tests.
   *
   * @return string
   *   A Lorem Ipsum text.
   */
  protected function getFieldBody() {
    return 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris dictum ultricies sapien id consequat.
Fusce tristique erat at dui ultricies, eu rhoncus odio rutrum. Praesent viverra mollis mauris a cursus.
Curabitur at condimentum orci. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus.
Praesent suscipit massa non pretium volutpat. Suspendisse id lacus facilisis, fringilla mauris vitae, tincidunt turpis.
Proin a euismod libero. Nam aliquet neque nulla, nec placerat libero accumsan id. Quisque sit amet consequat lacus.
Donec mauris erat, iaculis id nisl nec, dapibus posuere lectus. Sed ultrices libero id elit volutpat sagittis.
Donec a tortor ullamcorper, tempus lectus at, ultrices felis. Nam nibh magna, dictum in massa ut, ornare venenatis enim.
Phasellus enim massa, condimentum eu sem vel, consectetur fermentum erat. Cras porttitor ut dolor interdum vehicula.
Vestibulum erat arcu, placerat quis gravida quis, venenatis vel magna. Pellentesque pellentesque lacus ut feugiat auctor.
Mauris libero magna, dictum in fermentum nec, blandit non augue.
Morbi sed viverra libero.Phasellus sem velit, sollicitudin in felis lacinia, suscipit auctor dolor.
Praesent dignissim dolor sed lobortis mattis.
Ut tristique, ligula sit amet condimentum dapibus, lorem nunc congue velit, et dictum augue leo sodales augue.
Maecenas eget mi ac massa sagittis malesuada. Fusce ac purus vel ipsum imperdiet vulputate.
Mauris vestibulum sapien sit amet elementum tincidunt. Aenean sollicitudin tortor pulvinar ante commodo sagittis.
Integer in nisi consequat, elementum felis in, consequat purus. Maecenas blandit ipsum id tellus accumsan, sit amet venenatis orci vestibulum.
Ut id erat venenatis, vehicula mi eget, gravida odio. Etiam dapibus purus in massa condimentum, vitae lobortis est aliquam.
Morbi tristique velit et sem varius rhoncus. In tincidunt sagittis libero. Integer interdum sit amet sem sit amet sodales.
Donec sit amet arcu sit amet leo tristique dignissim vel ut enim. Nulla faucibus lacus eu adipiscing semper. Sed ut sodales erat.
Sed mauris purus, tempor non eleifend et, mollis ut lacus. Etiam interdum velit justo, nec imperdiet nunc pulvinar sit amet.
Sed eu lacus eget augue laoreet vehicula id sed sem. Maecenas at condimentum massa, et pretium nulla. Aliquam sed nibh velit.
Quisque turpis lacus, sodales nec malesuada nec, commodo non purus.
Cras pellentesque, lectus ut imperdiet euismod, purus sem convallis tortor, ut fermentum elit nulla et quam.
Mauris luctus mattis enim non accumsan. Sed consequat sapien lorem, in ultricies orci posuere nec.
Fusce in mauris eu leo fermentum feugiat. Proin varius diam ante, non eleifend ipsum luctus sed.';
  }

  /**
   * Creates a field item data definition for the specified field type.
   *
   * @param string $field_type
   *   The field type to create the data definition for.
   *
   * @return \Drupal\Core\Field\TypedData\FieldItemDataDefinition
   *   The field item data definition object.
   */
  protected function createFieldItemDataDefinition($field_type) {
    // Create a mock field definition.
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition->method('getType')->willReturn($field_type);

    // Create a mock field storage definition.
    $storage_definition = $this->createMock(FieldStorageDefinitionInterface::class);
    $storage_definition->method('getPropertyDefinitions')->willReturn([]);
    $storage_definition->method('getMainPropertyName')->willReturn('value');

    $field_definition->method('getFieldStorageDefinition')->willReturn($storage_definition);

    return FieldItemDataDefinition::create($field_definition);
  }

  /**
   * Tests that excerpt creation handles null values without errors.
   */
  public function testCreateExcerptHandlesNullValuesWithoutError(): void {
    $field_data = [
      [
        'field_id' => 'content',
        'values' => [NULL, 'test content', NULL],
      ],
    ];
    $methods = [
      'createExcerptForFields' => ['test'],
      'createFallbackExcerpt' => 20,
    ];

    foreach ($methods as $method => $arg) {
      $method = new \ReflectionMethod($this->processor, $method);
      try {
        $excerpt = $method->invoke(
          $this->processor,
          $field_data,
          $arg,
        );
        $this->assertIsString($excerpt);
      }
      catch (\TypeError $e) {
        $this->fail('TypeError should not occur when handling null values: ' . $e->getMessage());
      }
    }
  }

}
