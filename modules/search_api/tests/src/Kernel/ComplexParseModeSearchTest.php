<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\IndexInterface;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests keyword searches with the Complex parse mode on the DB backend.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\search_api\parse_mode\Complex
 * @see \Drupal\search_api_db\Plugin\search_api\backend\Database
 */
#[RunTestsInSeparateProcesses]
class ComplexParseModeSearchTest extends KernelTestBase {

  use ExampleContentTrait;

  /**
   * The ID of the index used for testing.
   */
  protected const INDEX_ID = 'database_search_index';

  /**
   * The plugin ID of the parse mode being tested.
   */
  protected const PARSE_MODE_ID = 'complex';

  /**
   * Disable strict config schema checks for this test class.
   *
   * We do not touch index config anymore, but some shared fixtures may carry
   * optional keys not covered by schema in older branches.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'filter',
    'search_api',
    'search_api_db',
    'search_api_test_db',
    'search_api_test_example_content',
    'system',
    'text',
    'user',
  ];

  /**
   * The test index.
   */
  protected IndexInterface $index;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');

    $this->installConfig([
      'search_api',
      'search_api_db',
      'search_api_test_db',
      'search_api_test_example_content',
    ]);

    // The example trait provides helpers but also seeds content; for fully
    // deterministic IDs we’ll seed our own corpus instead.
    $this->setUpExampleStructure();

    // Seed deterministic mini-corpus (explicit IDs) for our boolean test cases.
    $this->addDoc(1, 'foo who bar going', 'We are in fux their baz');
    $this->addDoc(2, 'bar project', 'Nothing else mentioned here.');
    $this->addDoc(3, 'cogito ergo sum', '');
    $this->addDoc(4, 'pos', '');
    $this->addDoc(5, 'neg', '');
    $this->addDoc(6, 'quoted pos with -minus', '');
    $this->addDoc(7, 'quoted neg', '');
    $this->addDoc(8, "神奈川県　連携", '');
    $this->addDoc(9, 'buz', '');
    $this->addDoc(10, 'qux', '');
    $this->addDoc(11, 'baz qux', '');
    $this->addDoc(12, 'core grunt', '');
    $this->addDoc(13, 'foo', 'body has bar');
    $this->addDoc(14, 'baz', 'body contains qux quux phrase');
    $this->addDoc(15, 'bar', 'fux here');
    $this->addDoc(16, 'foo buz', '');

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = Index::load(self::INDEX_ID);
    $this->assertInstanceOf(Index::class, $index);
    $this->index = $index;

    // Index all items.
    $this->indexItems(self::INDEX_ID);
  }

  /**
   * Creates and saves a new document entity with the given data.
   *
   * @param int $id
   *   The unique identifier for the new document entity.
   * @param string $name
   *   The name of the new document entity.
   * @param string $body
   *   The body content of the new document entity.
   */
  protected function addDoc(int $id, string $name, string $body): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('entity_test_mulrev_changed');
    $e = $storage->create([
      'id' => $id,
      'name' => $name,
      'body' => $body,
    ]);
    $e->enforceIsNew(TRUE);
    $e->save();
  }

  /**
   * Tests parsing and execution of complex search keywords.
   *
   * This uses data provider complexKeywordsSearchesTestDataProvider() but
   * inlines it to improve performance.
   *
   * @see complexKeywordsSearchesTestDataProvider()
   */
  public function testComplexKeywordsSearches(): void {
    /** @var \Drupal\search_api\Plugin\search_api\parse_mode\Complex $plugin */
    $plugin = $this->container->get('plugin.manager.search_api.parse_mode')
      ->createInstance(self::PARSE_MODE_ID);
    $plugin->setConjunction('AND');

    foreach (static::complexKeywordsSearchesTestDataProvider() as $label => $data_set) {
      $data_set += ['expected_ids' => NULL];
      [
        'keys' => $keys,
        'expected' => $expected,
        'expected_ids' => $expected_ids,
      ] = $data_set;
      $parsed = $plugin->parseInput($keys);
      $this->assertSame($expected, $parsed, "Data set \"$label\": Failed to parse \"$keys\".");

      if ($expected_ids === NULL) {
        continue;
      }

      $results = $this->index->query()
        ->keys($parsed)
        ->execute();

      $ids = [];
      foreach ($results as $item) {
        $ids[] = (int) $item->getOriginalObject()->getValue()->id();
      }
      sort($ids);
      sort($expected_ids);

      $this->assertSame($expected_ids, $ids, "Data set \"$label\": Unexpected results.");
    }
  }

  /**
   * Provides test data sets for testComplexKeywordsSearches().
   *
   * @return array<string, array{'keys': string, 'expected': array|null, ?'expected_ids': list<int>}
   *   Associative array of test data sets, keyed by label and with values being
   *   associative arrays with the following keys:
   *   - keys: The keywords to parse.
   *   - expected: The expected parsed keywords.
   *   - expected_ids: (optional) In case the query should be executed, the
   *     expected results' IDs.
   *
   * @see testComplexKeywordsSearches()
   */
  public static function complexKeywordsSearchesTestDataProvider(): array {
    $all_except = static fn (array $ids) => array_diff(range(1, 16), $ids);
    return [
      'empty keywords' => [
        'keys' => '',
        'expected' => NULL,
        'expected_ids' => range(1, 16),
      ],
      'normal keywords' => [
        'keys' => 'foo bar',
        'expected' => [
          '#conjunction' => 'AND',
          'foo',
          'bar',
        ],
        'expected_ids' => [1, 13],
      ],
      'quoted phrase' => [
        'keys' => '"cogito ergo sum"',
        'expected' => [
          '#conjunction' => 'AND',
          'cogito ergo sum',
        ],
        'expected_ids' => [3],
      ],
      'single-word quotes' => [
        'keys' => '"foo"',
        'expected' => [
          '#conjunction' => 'AND',
          'foo',
        ],
        'expected_ids' => [1, 13, 16],
      ],
      'negated keyword' => [
        'keys' => 'NOT foo',
        'expected' => [
          '#negation' => TRUE,
          '#conjunction' => 'AND',
          'foo',
        ],
        'expected_ids' => $all_except([1, 13, 16]),
      ],
      'negated phrase' => [
        'keys' => 'NOT "cogito ergo sum"',
        'expected' => [
          '#negation' => TRUE,
          '#conjunction' => 'AND',
          'cogito ergo sum',
        ],
        'expected_ids' => $all_except([3]),
      ],
      'keywords with stand-alone dash' => [
        'keys' => 'foo - bar',
        'expected' => [
          '#conjunction' => 'AND',
          'foo',
          '-',
          'bar',
        ],
      ],
      'really complicated search' => [
        'keys' => 'pos NOT neg "quoted pos with -minus" - NOT "quoted neg"',
        'expected' => [
          '#conjunction' => 'AND',
          'pos',
          [
            '#negation' => TRUE,
            '#conjunction' => 'AND',
            'neg',
          ],
          'quoted pos with -minus',
          '-',
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
        'expected_ids' => [8],
      ],
      'grouped AND condition' => [
        'keys' => '(bar OR foo) AND (buz OR qux)',
        'expected' => [
          '#conjunction' => 'AND',
          [
            '#conjunction' => 'OR',
            'bar',
            'foo',
          ],
          [
            '#conjunction' => 'OR',
            'buz',
            'qux',
          ],
        ],
        'expected_ids' => [16],
      ],
      'grouped OR condition' => [
        'keys' => '(bar OR foo) OR (buz OR qux)',
        'expected' => [
          '#conjunction' => 'OR',
          [
            '#conjunction' => 'OR',
            'bar',
            'foo',
          ],
          [
            '#conjunction' => 'OR',
            'buz',
            'qux',
          ],
        ],
        'expected_ids' => [1, 2, 9, 10, 11, 13, 14, 15, 16],
      ],
      'nested negation and grouping' => [
        'keys' => 'foo AND (NOT bar OR (baz NOT "qux quux"))',
        'expected' => [
          '#conjunction' => 'AND',
          'foo',
          [
            '#conjunction' => 'OR',
            [
              '#negation' => TRUE,
              '#conjunction' => 'AND',
              'bar',
            ],
            [
              '#conjunction' => 'AND',
              'baz',
              [
                '#negation' => TRUE,
                '#conjunction' => 'AND',
                'qux quux',
              ],
            ],
          ],
        ],
        'expected_ids' => [1, 16],
      ],
      'nested negation and grouping with AND NOT' => [
        'keys' => 'foo AND (NOT bar OR (baz AND NOT "qux quux"))',
        'expected' => [
          '#conjunction' => 'AND',
          'foo',
          [
            '#conjunction' => 'OR',
            [
              '#negation' => TRUE,
              '#conjunction' => 'AND',
              'bar',
            ],
            [
              '#conjunction' => 'AND',
              'baz',
              [
                '#negation' => TRUE,
                '#conjunction' => 'AND',
                'qux quux',
              ],
            ],
          ],
        ],
        'expected_ids' => [1, 16],
      ],
      'complex mix of AND, OR and NOT' => [
        'keys' => '(foo OR bar) NOT (baz OR "qux quux") OR (core AND grunt)',
        'expected' => [
          '#conjunction' => 'OR',
          [
            '#conjunction' => 'AND',
            [
              '#conjunction' => 'OR',
              'foo',
              'bar',
            ],
            [
              '#negation' => TRUE,
              '#conjunction' => 'AND',
              [
                '#conjunction' => 'OR',
                'baz',
                'qux quux',
              ],
            ],
          ],
          [
            '#conjunction' => 'AND',
            'core',
            'grunt',
          ],
        ],
        'expected_ids' => [2, 12, 13, 15, 16],
      ],
      'negation with nested groups and phrases' => [
        'keys' => 'NOT (foo AND (bar OR "baz qux"))',
        'expected' => [
          '#negation' => TRUE,
          '#conjunction' => 'AND',
          [
            '#conjunction' => 'AND',
            'foo',
            [
              '#conjunction' => 'OR',
              'bar',
              'baz qux',
            ],
          ],
        ],
        'expected_ids' => $all_except([1, 13]),
      ],
      'mixed operators with nested grouping' => [
        'keys' => 'foo OR (bar NOT (baz OR qux)) AND "quoted phrase"',
        'expected' => [
          '#conjunction' => 'AND',
          [
            '#conjunction' => 'OR',
            'foo',
            [
              '#conjunction' => 'AND',
              'bar',
              [
                '#negation' => TRUE,
                '#conjunction' => 'AND',
                [
                  '#conjunction' => 'OR',
                  'baz',
                  'qux',
                ],
              ],
            ],
          ],
          'quoted phrase',
        ],
        'expected_ids' => [],
      ],
    ];
  }

}
