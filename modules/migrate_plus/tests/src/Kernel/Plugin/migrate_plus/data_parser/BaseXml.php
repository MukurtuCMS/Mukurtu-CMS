<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_plus\Kernel\Plugin\migrate_plus\data_parser;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate_plus\DataParserPluginInterface;
use Drupal\migrate_plus\DataParserPluginManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test of the data_parser SimpleXml migrate_plus plugin.
 */
#[Group('migrate_plus')]
#[RunTestsInSeparateProcesses]
abstract class BaseXml extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['migrate', 'migrate_plus'];

  /**
   * Path for the xml file.
   */
  protected ?string $path = NULL;

  /**
   * The plugin manager.
   */
  protected ?DataParserPluginManager $pluginManager = NULL;

  /**
   * The plugin configuration.
   */
  protected ?array $configuration = NULL;

  /**
   * The expected result.
   */
  protected ?array $expected = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->path = $this->container->get('module_handler')
      ->getModule('migrate_plus')->getPath();
    $this->pluginManager = $this->container
      ->get('plugin.manager.migrate_plus.data_parser');
    $this->configuration = [
      'plugin' => 'url',
      'data_fetcher_plugin' => 'file',
      'data_parser_plugin' => $this->getDataParserPluginId(),
      'destination' => 'node',
      'urls' => [],
      'ids' => ['id' => ['type' => 'integer']],
      'fields' => [
        [
          'name' => 'id',
          'label' => 'Id',
          'selector' => '@id',
        ],
        [
          'name' => 'values',
          'label' => 'Values',
          'selector' => 'values',
        ],
      ],
      'item_selector' => '/items/item',
    ];
    $this->expected = [
      [
        'Value 1',
        'Value 2',
      ],
      [
        'Value 1 (single)',
      ],
    ];
  }

  /**
   * Tests current URL of parsed XML item.
   */
  public function testCurrentUrl(): void {
    $urls = [
      $this->path . '/tests/data/xml_current_url1.xml',
      $this->path . '/tests/data/xml_current_url2.xml',
    ];
    $this->configuration['urls'] = $urls;
    $parser = $this->getParser();

    // First 2 items available in the first URL.
    $parser->rewind();
    $this->assertEquals($urls[0], $parser->currentUrl());
    $parser->next();
    $this->assertEquals($urls[0], $parser->currentUrl());

    // Third item available in the second URL.
    $parser->next();
    $this->assertEquals($urls[1], $parser->currentUrl());
  }

  /**
   * Tests reducing single values.
   */
  public function testReduceSingleValue(): void {
    $url = $this->path . '/tests/data/xml_reduce_single_value.xml';
    $this->configuration['urls'][0] = $url;
    $this->assertEquals($this->expected, $this->parseResults($this->getParser()));
  }

  /**
   * Tests retrieving single value from element with attributes.
   */
  public function testSingleValueWithAttributes() {
    $urls = [
      $this->path . '/tests/data/xml_persons.xml',
    ];
    $this->configuration['urls'] = $urls;
    $this->configuration['item_selector'] = '/persons/person';
    $this->configuration['fields'] = [
      [
        'name' => 'id',
        'label' => 'Id',
        'selector' => 'id',
      ],
      [
        'name' => 'child',
        'label' => 'child',
        'selector' => 'children/child',
      ],
    ];

    $names = [];
    foreach ($this->getParser() as $item) {
      $names[] = (string) $item['child']->name;
    }

    $expected_names = ['Elizabeth Junior', 'George Junior', 'Lucy'];
    $this->assertEquals($expected_names, $names);
  }

  /**
   * Tests retrieval a value with multiple items.
   */
  public function testMultipleItems(): void {
    $this->configuration['urls'] = [
      $this->path . '/tests/data/xml_multiple_items.xml',
    ];
    $this->configuration['fields'] = [
      [
        'name' => 'id',
        'label' => 'Id',
        'selector' => 'Id',
      ],
      [
        'name' => 'sub_items1',
        'label' => 'Sub items 1',
        'selector' => 'Values1/SubItem',
      ],
      [
        'name' => 'sub_items2',
        'label' => 'Sub items 2',
        'selector' => 'Values2/SubItem',
      ],
    ];

    $parser = $this->getParser();
    $parser->next();

    // Transform SimpleXMLElements to arrays.
    $item = json_decode(json_encode($parser->current()), TRUE);
    $sub_items1 = array_column($item['sub_items1'], 'Id');
    $this->assertEquals(['1', '2'], $sub_items1);
    $this->assertEquals(['3', '4'], $item['sub_items2']);
  }

  /**
   * Tests that the item selector can traverse back up the node at the end.
   *
   * Traversals like these are redundant and highly inefficient, and filtering
   * should be done before the final predicate is specified.
   */
  public function testParentTraversalMatch(): void {
    $url = $this->path . '/tests/data/xml_items.xml';
    $this->configuration['urls'][0] = $url;
    $this->configuration['item_selector'] = '/items/item/values[value="Value 3"]/..';
    $this->expected = [
      ['Value 3'],
    ];
    $this->assertEquals($this->expected, $this->parseResults($this->getParser()));
  }

  /**
   * Tests predicate matching.
   *
   * @dataProvider predicateMatchProvider
   */
  #[DataProvider('predicateMatchProvider')]
  public function testPredicateMatch(string $item_selector, array $expected_items): void {
    $url = $this->path . '/tests/data/xml_items.xml';
    $this->configuration['urls'][0] = $url;
    $this->configuration['item_selector'] = $item_selector;
    $this->assertEquals($expected_items, $this->parseResults($this->getParser()));
  }

  /**
   * Data provider for testPredicateMatch().
   *
   * @return array
   *   An array containing input values and expected output values.
   */
  public static function predicateMatchProvider(): array {
    return [
      // Pick up items with the "odd" parity attribute.
      'odd parity' => [
        'item_selector' => '/items/item[@parity="odd"]',
        'expected_items' => [
          ['Value 1'],
          ['Value 3'],
        ],
      ],
      // Pick up items with the "even" parity attribute.
      'even parity' => [
        'item_selector' => '/items/item[@parity="even"]',
        'expected_items' => [
          ['Value 2'],
        ],
      ],
      // Pick up items with the special attribute on the child.
      'special attribute' => [
        'item_selector' => '/items/item[condition[@special="true"]]',
        'expected_items' => [
          ['Special value'],
        ],
      ],
    ];
  }

  /**
   * Converts the results into a standardized data format to compare.
   *
   * @param \Traversable $results
   *   An iterable data result to parse.
   * @param string $property
   *   The property of the result set.
   *
   * @return array
   *   The results.
   */
  protected function parseResults(\Traversable $results, string $property = 'values'): array {
    $data = [];
    foreach ($results as $item) {
      $values = [];
      foreach ($item[$property] as $value) {
        $values[] = (string) $value;
      }
      $data[] = $values;
    }
    return $data;
  }

  /**
   * Returns a parse object with active configuration.
   *
   * @return \Drupal\migrate_plus\DataParserPluginInterface
   *   Data parser object.
   */
  protected function getParser(): DataParserPluginInterface {
    return $this->pluginManager->createInstance($this->getDataParserPluginId(), $this->configuration);
  }

  /**
   * Returns the data parser plugin ID.
   *
   * @return string
   *   The data parser plugin ID.
   */
  abstract protected function getDataParserPluginId(): string;

}
