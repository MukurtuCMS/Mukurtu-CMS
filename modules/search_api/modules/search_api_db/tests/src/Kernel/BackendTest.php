<?php

namespace Drupal\Tests\search_api_db\Kernel;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Database\Database as CoreDatabase;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Event\IndexingItemsEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\search_api\data_type\value\TextToken;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValue;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api_db\DatabaseCompatibility\GenericDatabase;
use Drupal\search_api_db\Plugin\search_api\backend\Database;
use Drupal\search_api_db\Tests\DatabaseTestsTrait;
use Drupal\Tests\search_api\Kernel\BackendTestBase;
use Drupal\Tests\search_api\Kernel\TestLogger;

// cspell:ignore foob fooblob
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests index and search capabilities using the Database search backend.
 *
 * @see \Drupal\search_api_db\Plugin\search_api\backend\Database
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class BackendTest extends BackendTestBase {

  use DatabaseTestsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api_db',
    'search_api_test_db',
  ];

  /**
   * {@inheritdoc}
   */
  protected $serverId = 'database_search_server';

  /**
   * {@inheritdoc}
   */
  protected $indexId = 'database_search_index';

  /**
   * The test logger installed in the container.
   *
   * Will throw exceptions whenever a warning or error is logged.
   *
   * @var \Drupal\Tests\search_api\Kernel\TestLogger
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create a dummy table that will cause a naming conflict with the backend's
    // default table names, thus testing whether it correctly reacts to such
    // conflicts.
    $db = \Drupal::database();
    $db->schema()->createTable('search_api_db_database_search_index', [
      'fields' => [
        'id' => [
          'type' => 'int',
        ],
      ],
    ]);

    $this->installConfig(['search_api_test_db']);

    // Add additional fields to the search index that have the same ID as
    // column names used by this backend, to see whether this leads to any
    // conflicts.
    $index = $this->getIndex();
    $fields_helper = \Drupal::getContainer()->get('search_api.fields_helper');
    $column_names = [
      'item_id',
      'field_name',
      'word',
      'score',
      'value',
    ];
    $field_info = [
      'datasource_id' => 'entity:entity_test_mulrev_changed',
      'property_path' => 'type',
      'type' => 'string',
    ];
    foreach ($column_names as $column_name) {
      $field_info['label'] = "Test field $column_name";
      $field = $fields_helper->createField($index, $column_name, $field_info);
      $index->addField($field);
    }
    $index->save();

    // If the driver is MySQL, make sure the "ONLY_FULL_GROUP_BY" SQL mode is
    // active so we can spot any problems with that.
    if ($db->driver() === 'mysql') {
      $sql_mode = $db->query("SELECT @@SESSION.sql_mode;")->fetchField();
      if (!str_contains($sql_mode, 'ONLY_FULL_GROUP_BY')) {
        $db->query("SET SESSION sql_mode = ?", ["$sql_mode,ONLY_FULL_GROUP_BY"]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    // Set a logger that will throw exceptions when warnings/errors are logged.
    $this->logger = new TestLogger('');
    $container->set('logger.factory', $this->logger);
    $container->set('logger.channel.search_api', $this->logger);
    $container->set('logger.channel.search_api_db', $this->logger);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkBackendSpecificFeatures() {
    $this->checkMultiValuedInfo();
    $this->searchWithRandom();
    $this->setServerMatchMode();
    $this->searchSuccessPartial();
    $this->setServerMatchMode('prefix');
    $this->searchSuccessStartsWith();
    $this->searchSuccessPhrase();
    $this->editServerMinChars();
    $this->searchSuccessMinChars();
    $this->checkUnknownOperator();
    $this->checkDbQueryAlter();
    $this->checkFieldIdChanges();
  }

  /**
   * {@inheritdoc}
   */
  protected function backendSpecificRegressionTests() {
    $this->regressionTest2557291();
    $this->regressionTest2511860();
    $this->regressionTest2846932();
    $this->regressionTest2926733();
    $this->regressionTest2938646();
    $this->regressionTest2925464();
    $this->regressionTest2994022();
    $this->regressionTest2916534();
    $this->regressionTest2873023();
    $this->regressionTest3199355();
    $this->regressionTest3225675();
    $this->regressionTest3258802();
    $this->regressionTest3227268();
    $this->regressionTest3397017();
    $this->regressionTest3436123();
  }

  /**
   * Tests that all tables and all columns have been created.
   */
  protected function checkServerBackend() {
    $db_info = $this->getIndexDbInfo();
    $normalized_storage_table = $db_info['index_table'];
    $field_infos = $db_info['field_tables'];

    $expected_fields = [
      'body',
      'category',
      'created',
      'field_name',
      'id',
      'item_id',
      'keywords',
      'name',
      'score',
      'search_api_datasource',
      'search_api_language',
      'type',
      'value',
      'width',
      'word',
    ];
    $actual_fields = array_keys($field_infos);
    sort($actual_fields);
    $this->assertEquals($expected_fields, $actual_fields, 'All expected field tables were created.');

    $this->assertTrue(\Drupal::database()->schema()->tableExists($normalized_storage_table), 'Normalized storage table exists.');
    $this->assertHasPrimaryKey($normalized_storage_table, 'Normalized storage table has a primary key.');
    foreach ($field_infos as $field_id => $field_info) {
      if ($field_id != 'search_api_id') {
        $this->assertTrue(\Drupal::database()
          ->schema()
          ->tableExists($field_info['table']));
      }
      else {
        $this->assertEmpty($field_info['table']);
      }
      $this->assertTrue(\Drupal::database()->schema()->fieldExists($normalized_storage_table, $field_info['column']), new FormattableMarkup('Field column %column exists', ['%column' => $field_info['column']]));
    }
  }

  /**
   * Checks whether changes to the index's fields are picked up by the server.
   */
  protected function updateIndex() {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->getIndex();

    // Remove a field from the index and check if the change is matched in the
    // server configuration.
    $field = $index->getField('keywords');
    if (!$field) {
      throw new \Exception();
    }
    $index->removeField('keywords');
    $index->save();

    $index_fields = array_keys($index->getFields());
    // Include the three "magic" fields we're indexing with the DB backend.
    $index_fields[] = 'search_api_datasource';
    $index_fields[] = 'search_api_language';

    $db_info = $this->getIndexDbInfo();
    $server_fields = array_keys($db_info['field_tables']);

    sort($index_fields);
    sort($server_fields);
    $this->assertEquals($index_fields, $server_fields);

    // Add the field back for the next assertions.
    $index->addField($field)->save();
  }

  /**
   * Verifies that the generated table names are correct.
   */
  protected function checkTableNames() {
    $this->assertEquals('search_api_db_database_search_index_1', $this->getIndexDbInfo()['index_table']);
    $this->assertEquals('search_api_db_database_search_index_text', $this->getIndexDbInfo()['field_tables']['body']['table']);
  }

  /**
   * Verifies that the stored information about multi-valued fields is correct.
   */
  protected function checkMultiValuedInfo() {
    $db_info = $this->getIndexDbInfo();
    $field_info = $db_info['field_tables'];

    $fields = [
      'name',
      'body',
      'type',
      'keywords',
      'category',
      'width',
      'search_api_datasource',
      'search_api_language',
    ];
    $multi_valued = [
      'name',
      'body',
      'keywords',
    ];
    foreach ($fields as $field_id) {
      $this->assertArrayHasKey($field_id, $field_info, "Field info saved for field $field_id.");
      if (in_array($field_id, $multi_valued)) {
        $this->assertFalse(empty($field_info[$field_id]['multi-valued']), "Field $field_id is stored as multi-value.");
      }
      else {
        $this->assertTrue(empty($field_info[$field_id]['multi-valued']), "Field $field_id is not stored as multi-value.");
      }
    }
  }

  /**
   * Edits the server to sets the match mode.
   *
   * @param string $match_mode
   *   The matching mode to set – "words", "partial" or "prefix".
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setServerMatchMode($match_mode = 'partial') {
    $server = $this->getServer();
    $backend_config = $server->getBackendConfig();
    $backend_config['matching'] = $match_mode;
    $server->setBackendConfig($backend_config);
    $this->assertTrue((bool) $server->save(), 'The server was successfully edited.');
    $this->resetEntityCache();
  }

  /**
   * Tests whether random searches work.
   */
  protected function searchWithRandom() {
    // Run the query 5 times, using random sorting as the first sort and verify
    // that the results are not always the same.
    $first_result = NULL;
    $second_result = NULL;
    for ($i = 1; $i <= 5; $i++) {
      $results = $this->buildSearch('foo', [], NULL, FALSE)
        ->sort('search_api_random')
        ->sort('id')
        ->execute();

      $result_ids = array_keys($results->getResultItems());
      if ($first_result === NULL) {
        $first_result = $second_result = $result_ids;
      }
      elseif ($result_ids !== $first_result) {
        $second_result = $result_ids;
      }

      // Make sure the search still returned the expected items.
      $this->assertCount(4, $result_ids);
      sort($result_ids);
      $this->assertEquals($this->getItemIds([1, 2, 4, 5]), $result_ids);
    }
    $this->assertNotEquals($first_result, $second_result);

    // Run only for MySQL and Postgres because SQLite does not support seeds
    // right now.
    // Run the query 3 times and compare the results.
    if (in_array(\Drupal::database()->driver(), ['mysql', 'pgsql'])) {
      $seed = rand(10000, 100000);
      $results = [];
      for ($i = 1; $i <= 3; $i++) {
        $query = $this->buildSearch('foo', [], NULL, FALSE)
          ->sort('search_api_random')
          ->sort('id');

        $query->setOption('search_api_random_sort', ['seed' => $seed]);
        $results[$i] = $query->execute()->getResultItems();
        $this->assertCount(4, $results[$i]);
      }

      // Check if results are the same
      $this->assertEquals($results[1], $results[2]);
      $this->assertEquals($results[2], $results[3]);
    }
  }

  /**
   * Tests whether partial searches work.
   */
  protected function searchSuccessPartial() {
    $results = $this->buildSearch('foobaz')->range(0, 1)->execute();
    $this->assertResults([1], $results, 'Partial search for »foobaz«');

    $results = $this->buildSearch('foo', [], [], FALSE)
      ->sort('search_api_relevance', QueryInterface::SORT_DESC)
      ->sort('id')
      ->execute();
    $this->assertResults([1, 2, 4, 3, 5], $results, 'Partial search for »foo«');

    $results = $this->buildSearch('foo tes')->execute();
    $this->assertResults([1, 2, 3, 4], $results, 'Partial search for »foo tes«');

    $results = $this->buildSearch('oob est')->execute();
    $this->assertResults([1, 2, 3], $results, 'Partial search for »oob est«');

    $results = $this->buildSearch('foo nonexistent')->execute();
    $this->assertResults([], $results, 'Partial search for »foo nonexistent«');

    $results = $this->buildSearch('bar nonexistent')->execute();
    $this->assertResults([], $results, 'Partial search for »bar nonexistent«');

    $keys = [
      '#conjunction' => 'AND',
      'oob',
      [
        '#conjunction' => 'OR',
        'est',
        'nonexistent',
      ],
    ];
    $results = $this->buildSearch($keys)->execute();
    $this->assertResults([1, 2, 3], $results, 'Partial search for complex keys');

    $results = $this->buildSearch('foo', ['category,item_category'], [], FALSE)
      ->sort('id', QueryInterface::SORT_DESC)
      ->execute();
    $this->assertResults([2, 1], $results, 'Partial search for »foo« with additional filter');

    $query = $this->buildSearch();
    $conditions = $query->createAndAddConditionGroup('OR');
    $conditions->addCondition('name', 'test');
    $conditions->addCondition('body', 'test');
    $results = $query->execute();
    $this->assertResults([1, 2, 3, 4], $results, 'Partial search with multi-field fulltext filter');
  }

  /**
   * Tests whether prefix matching works.
   */
  protected function searchSuccessStartsWith() {
    $results = $this->buildSearch('foobaz')->range(0, 1)->execute();
    $this->assertResults([1], $results, 'Prefix search for »foobaz«');

    $results = $this->buildSearch('foo', [], [], FALSE)
      ->sort('search_api_relevance', QueryInterface::SORT_DESC)
      ->sort('id')
      ->execute();
    $this->assertResults([1, 2, 4, 3, 5], $results, 'Prefix search for »foo«');

    $results = $this->buildSearch('foo tes')->execute();
    $this->assertResults([1, 2, 3, 4], $results, 'Prefix search for »foo tes«');

    $results = $this->buildSearch('oob est')->execute();
    $this->assertResults([], $results, 'Prefix search for »oob est«');

    $results = $this->buildSearch('foo nonexistent')->execute();
    $this->assertResults([], $results, 'Prefix search for »foo nonexistent«');

    $results = $this->buildSearch('bar nonexistent')->execute();
    $this->assertResults([], $results, 'Prefix search for »bar nonexistent«');

    $keys = [
      '#conjunction' => 'AND',
      'foob',
      [
        '#conjunction' => 'OR',
        'tes',
        'nonexistent',
      ],
    ];
    $results = $this->buildSearch($keys)->execute();
    $this->assertResults([1, 2, 3], $results, 'Prefix search for complex keys');

    $results = $this->buildSearch('foo', ['category,item_category'], [], FALSE)
      ->sort('id', QueryInterface::SORT_DESC)
      ->execute();
    $this->assertResults([2, 1], $results, 'Prefix search for »foo« with additional filter');

    $query = $this->buildSearch();
    $conditions = $query->createAndAddConditionGroup('OR');
    $conditions->addCondition('name', 'test');
    $conditions->addCondition('body', 'test');
    $results = $query->execute();
    $this->assertResults([1, 2, 3, 4], $results, 'Prefix search with multi-field fulltext filter');
  }

  /**
   * Tests whether phrase matching works.
   */
  protected function searchSuccessPhrase(): void {
    // Searching for keywords _without_ double-quotes should return documents
    // that contain all of those keywords in any order.
    $results = $this->buildSearch('foo baz')->execute();
    $this->assertResults([1, 4, 5], $results);

    $results = $this->buildSearch('foo bar baz')->execute();
    $this->assertResults([1, 5], $results);

    // Searching for keywords _with_ double-quotes should return only documents
    // that contain those keywords sequentially.
    $results = $this->buildSearch('"foo baz"')->execute();
    $this->assertResults([4], $results);

    $results = $this->buildSearch('"foo bar baz"')->execute();
    $this->assertResults([1], $results);

    // Add a test entity with more complex text to test a few edge cases.
    $long_token_1 = str_repeat('foo', 12);
    $long_token_2 = str_repeat('bar', 12);
    $entity = $this->addTestEntity(6, [
      'body' => "foo no bar $long_token_1 $long_token_2 su baz 000 quux",
      'type' => 'article',
    ]);
    $this->indexItems($this->indexId);

    // As this should also test the handling of short (ignored) tokens in a
    // phrase search, we rely on "min_chars" being set to 3, so make sure this
    // is indeed the case.
    $min_chars = $this->getServer()->getBackendConfig()['min_chars'];
    $this->assertEquals(3, $min_chars);

    $results = $this->buildSearch("\"foo no bar\"")->execute();
    $this->assertResults([1, 6], $results);

    $results = $this->buildSearch("\"$long_token_1 $long_token_2\"")->execute();
    $this->assertResults([6], $results);

    $results = $this->buildSearch("\"bar $long_token_1 $long_token_2 su baz\"")->execute();
    $this->assertResults([6], $results);

    $results = $this->buildSearch("\"foo no bar\" \"$long_token_1 $long_token_2 su baz\"")->execute();
    $this->assertResults([6], $results);

    $results = $this->buildSearch("\"foo no bar $long_token_1 $long_token_2 su baz\"")->execute();
    $this->assertResults([6], $results);

    $long_token_2 = mb_substr($long_token_2, -2);
    $results = $this->buildSearch("\"$long_token_1 $long_token_2 su baz\"")->execute();
    $this->assertResults([], $results);

    // '0' should be searchable. See also testCleanNumericString().
    $results = $this->buildSearch("\"baz 0\"")->execute();
    $this->assertResults([6], $results);

    // Delete the new test entity again so it doesn't mess up the tests in other
    // methods.
    $entity->delete();
  }

  /**
   * Edits the server to change the "Minimum word length" setting.
   */
  protected function editServerMinChars() {
    $server = $this->getServer();
    $backend_config = $server->getBackendConfig();
    $backend_config['min_chars'] = 4;
    $backend_config['matching'] = 'words';
    $server->setBackendConfig($backend_config);
    $success = (bool) $server->save();
    $this->assertTrue($success, 'The server was successfully edited.');

    $this->clearIndex();
    $this->indexItems($this->indexId);

    $this->resetEntityCache();
  }

  /**
   * Tests the results of some test searches with minimum word length of 4.
   */
  protected function searchSuccessMinChars() {
    $results = $this->getIndex()->query()->keys('test')->range(1, 2)->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Search for »test« returned correct number of results.');
    $this->assertEquals($this->getItemIds([4, 1]), array_keys($results->getResultItems()), 'Search for »test« returned correct result.');
    $this->assertEmpty($results->getIgnoredSearchKeys());
    $this->assertEmpty($results->getWarnings());

    $query = $this->buildSearch();
    $conditions = $query->createAndAddConditionGroup('OR');
    $conditions->addCondition('name', 'test');
    $conditions->addCondition('body', 'test');
    $results = $query->execute();
    $this->assertResults([1, 2, 3, 4], $results, 'Search with multi-field fulltext filter');

    $results = $this->buildSearch(NULL, ['body,test foobar'])->execute();
    $this->assertResults([3], $results, 'Search with multi-term fulltext filter');

    $results = $this->getIndex()->query()->keys('test foo')->execute();
    $this->assertResults([2, 4, 1, 3], $results, 'Search for »test foo«', ['foo']);

    $results = $this->buildSearch('foo', ['type,item'])->execute();
    $this->assertResults([1, 2, 3], $results, 'Search for »foo«', ['foo'], ['No valid search keys were present in the query.']);

    $keys = [
      '#conjunction' => 'AND',
      'test',
      [
        '#conjunction' => 'OR',
        'baz',
        'foobar',
      ],
      [
        '#conjunction' => 'OR',
        '#negation' => TRUE,
        'bar',
        'fooblob',
      ],
    ];
    $results = $this->buildSearch($keys)->execute();
    $this->assertResults([3], $results, 'Complex search 1', ['baz', 'bar']);

    $keys = [
      '#conjunction' => 'AND',
      'test',
      [
        '#conjunction' => 'OR',
        'baz',
        'foobar',
      ],
      [
        '#conjunction' => 'OR',
        '#negation' => TRUE,
        'bar',
        'fooblob',
      ],
    ];
    $results = $this->buildSearch($keys)->execute();
    $this->assertResults([3], $results, 'Complex search 2', ['baz', 'bar']);

    $results = $this->buildSearch(NULL, ['keywords,orange'])->execute();
    $this->assertResults([1, 2, 5], $results, 'Filter query 1 on multi-valued field');

    $conditions = [
      'keywords,orange',
      'keywords,apple',
    ];
    $results = $this->buildSearch(NULL, $conditions)->execute();
    $this->assertResults([2], $results, 'Filter query 2 on multi-valued field');

    $results = $this->buildSearch()->addCondition('keywords', 'orange', '<>')->execute();
    $this->assertResults([3, 4], $results, 'Negated filter on multi-valued field');

    $results = $this->buildSearch()->addCondition('keywords', NULL)->execute();
    $this->assertResults([3], $results, 'Query with NULL filter');

    $results = $this->buildSearch()->addCondition('keywords', NULL, '<>')->execute();
    $this->assertResults([1, 2, 4, 5], $results, 'Query with NOT NULL filter');
  }

  /**
   * Checks that an unknown operator throws an exception.
   */
  protected function checkUnknownOperator() {
    try {
      $this->buildSearch()
        ->addCondition('id', 1, '!=')
        ->execute();
      $this->fail('Unknown operator "!=" did not throw an exception.');
    }
    catch (SearchApiException) {
      $this->assertTrue(TRUE, 'Unknown operator "!=" threw an exception.');
    }
  }

  /**
   * Checks whether the module's specific alter hook and event work correctly.
   */
  protected function checkDbQueryAlter() {
    $query = $this->buildSearch();
    $query->setOption('search_api_test_db_search_api_db_query_alter', TRUE);
    $results = $query->execute();
    $this->assertResults([], $results, 'Query triggering custom alter hook');

    $query = $this->buildSearch();
    $query->setOption('search_api_test_db.event.query_pre_execute.1', TRUE);
    $results = $query->execute();
    $this->assertResults([], $results, 'Query triggering custom alter event 1');

    $query = $this->buildSearch();
    $query->setOption('search_api_test_db.event.query_pre_execute.2', TRUE);
    $results = $query->execute();
    $this->assertResults([], $results, 'Query triggering custom alter event 2');
  }

  /**
   * Checks that field ID changes are treated correctly (without re-indexing).
   */
  protected function checkFieldIdChanges() {
    $this->getIndex()
      ->renameField('type', 'foobar')
      ->save();

    $results = $this->buildSearch(NULL, ['foobar,item'])->execute();
    $this->assertResults([1, 2, 3], $results, 'Search after renaming a field.');
    $this->getIndex()->renameField('foobar', 'type')->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkSecondServer() {
    /** @var \Drupal\search_api\ServerInterface $second_server */
    $second_server = Server::create([
      'id' => 'test2',
      'backend' => 'search_api_db',
      'backend_config' => [
        'database' => 'default:default',
      ],
    ]);
    $second_server->save();
    $query = $this->buildSearch();
    try {
      $second_server->search($query);
      $this->fail('Could execute a query for an index on a different server.');
    }
    catch (SearchApiException) {
      $this->assertTrue(TRUE, 'Executing a query for an index on a different server throws an exception.');
    }
    $second_server->delete();
  }

  /**
   * Tests the case-sensitivity of fulltext searches.
   *
   * @see https://www.drupal.org/node/2557291
   */
  protected function regressionTest2557291() {
    $results = $this->buildSearch('case')->execute();
    $this->assertResults([1], $results, 'Search for lowercase "case"');

    $results = $this->buildSearch('Case')->execute();
    $this->assertResults([1, 3], $results, 'Search for capitalized "Case"');

    $results = $this->buildSearch('CASE')->execute();
    $this->assertResults([], $results, 'Search for non-existent uppercase version of "CASE"');

    $results = $this->buildSearch('föö')->execute();
    $this->assertResults([1], $results, 'Search for keywords with umlauts');

    $results = $this->buildSearch('smile' . json_decode('"\u1F601"'))->execute();
    $this->assertResults([1], $results, 'Search for keywords with umlauts');

    $results = $this->buildSearch()->addCondition('keywords', 'grape', '<>')->execute();
    $this->assertResults([1, 3], $results, 'Negated filter on multi-valued field');
  }

  /**
   * Tests searching for multiple two-letter words.
   *
   * @see https://www.drupal.org/node/2511860
   */
  protected function regressionTest2511860() {
    $query = $this->buildSearch();
    $query->addCondition('body', 'ab');
    $query->addCondition('body', 'xy');
    $results = $query->execute();
    $this->assertEquals(5, $results->getResultCount(), 'Fulltext filters on short words do not change the result.');

    $query = $this->buildSearch();
    $query->addCondition('body', 'ab');
    $query->addCondition('body', 'ab');
    $results = $query->execute();
    $this->assertEquals(5, $results->getResultCount(), 'Fulltext filters on duplicate short words do not change the result.');
  }

  /**
   * Tests changing a field boost to a floating point value.
   *
   * @see https://www.drupal.org/node/2846932
   */
  protected function regressionTest2846932() {
    $index = $this->getIndex();
    $index->getField('body')->setBoost(0.8);
    $index->save();
  }

  /**
   * Tests indexing of text tokens with leading/trailing whitespace.
   *
   * @see https://www.drupal.org/node/2926733
   */
  protected function regressionTest2926733() {
    $index = $this->getIndex();
    $item_id = $this->getItemIds([1])[0];
    $fields_helper = \Drupal::getContainer()
      ->get('search_api.fields_helper');
    $item = $fields_helper->createItem($index, $item_id);
    $field = clone $index->getField('body');
    $value = new TextValue('test');
    $tokens = [];
    foreach (['test', ' test', '  test', 'test  ', ' test '] as $token) {
      $tokens[] = new TextToken($token);
    }
    $value->setTokens($tokens);
    $field->setValues([$value]);
    $item->setFields([
      'body' => $field,
    ]);
    $item->setFieldsExtracted(TRUE);
    $index->getServerInstance()->indexItems($index, [$item_id => $item]);

    // Make sure to re-index the proper version of the item to avoid confusing
    // the other tests.
    [$datasource_id, $raw_id] = Utility::splitCombinedId($item_id);
    $index->trackItemsUpdated($datasource_id, [$raw_id]);
    $this->indexItems($index->id());
  }

  /**
   * Tests indexing of items with boost.
   *
   * @see https://www.drupal.org/node/2938646
   */
  protected function regressionTest2938646() {
    $db_info = $this->getIndexDbInfo();
    $text_table = $db_info['field_tables']['body']['table'];
    $item_id = $this->getItemIds([1])[0];
    $select = \Drupal::database()->select($text_table, 't');
    $select
      ->fields('t', ['score'])
      ->condition('item_id', $item_id)
      ->condition('word', 'test');
    $select2 = clone $select;

    // Check old score.
    $old_score = $select
      ->execute()
      ->fetchField();
    $this->assertNotSame(FALSE, $old_score);
    $this->assertGreaterThan(0, $old_score);

    // Re-index item with higher boost.
    $index = $this->getIndex();
    $item = $this->container->get('search_api.fields_helper')
      ->createItem($index, $item_id);
    $item->setBoost(2);
    $indexed_ids = $this->indexItemDirectly($index, $item);
    $this->assertEquals([$item_id], $indexed_ids);

    // Verify the field scores changed accordingly.
    $new_score = $select2
      ->execute()
      ->fetchField();
    $this->assertNotSame(FALSE, $new_score);
    $this->assertEquals(2 * $old_score, $new_score);
  }

  /**
   * Tests changing of field types.
   *
   * @see https://www.drupal.org/node/2925464
   */
  protected function regressionTest2925464() {
    $index = $this->getIndex();

    // Changing the field type and, thus, column type, will cause a database
    // error on MySQL and Postgres due to illegal integer values.
    if (in_array(\Drupal::database()->driver(), ['mysql', 'pgsql'])) {
      $this->logger->setExpectedErrors(1);
    }

    $index->getField('category')->setType('integer');
    $index->save();

    $this->logger->assertAllExpectedErrorsEncountered();

    $index->getField('category')->setType('string');
    $index->save();

    $this->indexItems($index->id());
  }

  /**
   * Tests facets functionality for empty result sets.
   *
   * @see https://www.drupal.org/node/2994022
   */
  protected function regressionTest2994022() {
    $query = $this->buildSearch('nonexistent_search_term');
    $facets['category'] = [
      'field' => 'category',
      'limit' => 0,
      'min_count' => 0,
      'missing' => FALSE,
      'operator' => 'and',
    ];
    $query->setOption('search_api_facets', $facets);
    $results = $query->execute();
    $this->assertResults([], $results, 'Non-existent keyword');
    $expected = [
      ['count' => 0, 'filter' => '"article_category"'],
      ['count' => 0, 'filter' => '"item_category"'],
    ];
    $category_facets = $results->getExtraData('search_api_facets')['category'];
    usort($category_facets, [$this, 'facetCompare']);
    $this->assertEquals($expected, $category_facets, 'Correct facets were returned for minimum count 0');

    $query = $this->buildSearch('nonexistent_search_term');
    $conditions = $query->createAndAddConditionGroup('AND', ['facet:category']);
    $conditions->addCondition('category', 'article_category');
    $facets['category'] = [
      'field' => 'category',
      'limit' => 0,
      'min_count' => 0,
      'missing' => FALSE,
      'operator' => 'and',
    ];
    $query->setOption('search_api_facets', $facets);
    $results = $query->execute();
    $this->assertResults([], $results, 'Non-existent keyword with filter');
    $expected = [
      ['count' => 0, 'filter' => '"article_category"'],
      ['count' => 0, 'filter' => '"item_category"'],
    ];
    $category_facets = $results->getExtraData('search_api_facets')['category'];
    usort($category_facets, [$this, 'facetCompare']);
    $this->assertEquals($expected, $category_facets, 'Correct facets were returned for minimum count 0');
  }

  /**
   * Tests edge cases for partial matching.
   *
   * @see https://www.drupal.org/node/2916534
   */
  protected function regressionTest2916534() {
    $old = $this->getServer()->getBackendConfig()['matching'];
    $this->setServerMatchMode();

    $entity_id = count($this->entities) + 1;
    $entity = $this->addTestEntity($entity_id, [
      'name' => 'foo foobar foobar',
      'type' => 'article',
    ]);
    $this->indexItems($this->indexId);

    $results = $this->buildSearch('foo', [], ['name'])->execute();
    $this->assertResults([1, 2, 4, $entity_id], $results, 'Partial search for »foo«');

    $entity->delete();
    $this->setServerMatchMode($old);
  }

  /**
   * Tests whether keywords with special characters work correctly.
   *
   * @see https://www.drupal.org/node/2873023
   * @see https://www.drupal.org/node/3505734
   */
  protected function regressionTest2873023() {
    $keyword1 = 'regression@test@2873023';
    $keyword2 = 'FOO-03505734';

    $entity_id = count($this->entities) + 1;
    $entity = $this->addTestEntity($entity_id, [
      'name' => "$keyword1 $keyword2",
      'type' => 'article',
    ]);

    $index = $this->getIndex();
    $this->assertFalse($index->isValidProcessor('tokenizer'));
    $this->indexItems($this->indexId);
    $this->assertResults([$entity_id], $this->buildSearch($keyword1, [], ['name'])->execute());
    $this->assertResults([$entity_id], $this->buildSearch($keyword2, [], ['name'])->execute());

    $processor = \Drupal::getContainer()->get('search_api.plugin_helper')
      ->createProcessorPlugin($index, 'tokenizer');
    $index->addProcessor($processor);
    $index->save();
    $this->assertTrue($index->isValidProcessor('tokenizer'));
    $this->indexItems($this->indexId);
    $this->assertResults([$entity_id], $this->buildSearch($keyword1, [], ['name'])->execute());
    $this->assertResults([$entity_id], $this->buildSearch($keyword2, [], ['name'])->execute());

    $index->getProcessor('tokenizer')->setConfiguration([
      'spaces' => '\s',
    ]);
    $index->save();
    $this->indexItems($this->indexId);
    $this->assertResults([$entity_id], $this->buildSearch($keyword1, [], ['name'])->execute());
    $this->assertResults([$entity_id], $this->buildSearch($keyword2, [], ['name'])->execute());

    $index->removeProcessor('tokenizer');
    $index->save();
    $this->assertFalse($index->isValidProcessor('tokenizer'));

    $entity->delete();
    unset($this->entities[$entity_id]);
    $this->indexItems($this->indexId);
  }

  /**
   * Tests whether string field values with trailing spaces work correctly.
   *
   * @see https://www.drupal.org/node/3199355
   */
  protected function regressionTest3199355() {
    // Index all items before adding a new one, so we can better predict the
    // expected count.
    $this->indexItems($this->indexId);

    $entity_id = count($this->entities) + 1;
    $entity = $this->addTestEntity($entity_id, [
      'keywords' => ['foo', 'foo ', ' foo', ' foo '],
      'type' => 'article',
    ]);

    $count = $this->indexItems($this->indexId);
    $this->assertEquals(1, $count);
    $results = $this->buildSearch()
      ->addCondition('keywords', 'foo ')
      ->execute();
    $this->assertResults([$entity_id], $results, 'String filter with trailing space');

    $entity->delete();
    unset($this->entities[$entity_id]);
  }

  /**
   * Tests whether scoring is correct when multiple fields have the same boost.
   *
   * @see https://www.drupal.org/node/3225675
   */
  protected function regressionTest3225675() {
    // Set match mode to "partial" and the same field boost for both "body" and
    // "name".
    $this->setServerMatchMode();
    $index = $this->getIndex();
    $index->getField('name')->setBoost(1.0);
    $index->getField('body')->setBoost(1.0);
    $index->save();
    $this->indexItems($this->indexId);

    // Item 2 has "test" in both name and body, item 3 has it only in body, so
    // 2 should have a greater score. If the bug is present, both would have
    // same score.
    $results = $this->buildSearch('test', [], NULL, FALSE)
      ->addCondition('id', [2, 3], 'IN')
      ->sort('search_api_relevance', QueryInterface::SORT_DESC)
      ->execute();

    $resultItems = array_values($results->getResultItems());
    $this->assertLessThan($resultItems[0]->getScore(), $resultItems[1]->getScore());

    // Reset match mode and field boosts.
    $this->setServerMatchMode('words');
    $index = $this->getIndex();
    $index->getField('name')->setBoost(5);
    $index->getField('body')->setBoost(0.8);
    $index->save();
    $this->indexItems($this->indexId);
  }

  /**
   * Tests whether unknown field types are handled correctly.
   *
   * @see https://www.drupal.org/node/3258802
   */
  protected function regressionTest3258802(): void {
    $this->enableModules(['search_api_test']);

    $index = $this->getIndex();
    $type_field = $index->getField('type');
    $this->assertEquals('string', $type_field->getType());
    $type_field->setType('search_api_test_unsupported');
    $index->save();
    // No tasks should have been created.
    $task_manager = \Drupal::getContainer()->get('search_api.task_manager');
    $this->assertEquals(0, $task_manager->getTasksCount());
    // Reindexing should work fine.
    $index->clear();
    $this->assertEquals(5, $this->indexItems($this->indexId));

    $results = $index->query()->addCondition('type', 'article')->execute();
    $this->assertResults([4, 5], $results, 'Search with filter on field with unknown type');

    $index = $this->getIndex();
    $type_field = $index->getField('type');
    $this->assertEquals('search_api_test_unsupported', $type_field->getType());
    $type_field->setType('string');
    $index->save();
    // No tasks should have been created.
    $tasks_count = $task_manager->getTasksCount();
    $this->assertEquals(0, $tasks_count);
    $this->indexItems($this->indexId);

    $this->disableModules(['search_api_test']);
  }

  /**
   * Tests whether the text table's "item_id" column has the correct collation.
   *
   * This check is only active on MySQL.
   *
   * @see https://www.drupal.org/node/3227268
   *
   * @see \Drupal\search_api_db\DatabaseCompatibility\MySql::alterNewTable()
   */
  protected function regressionTest3227268(): void {
    $database = \Drupal::database();
    if ($database->driver() !== 'mysql') {
      return;
    }
    $db_info = $this->getIndexDbInfo();
    $text_table = $db_info['field_tables']['body']['table'];
    $this->assertTrue(\Drupal::database()->schema()->tableExists($text_table));
    $sql = "SHOW FULL COLUMNS FROM {{$text_table}}";
    $collations = [];
    foreach ($database->query($sql) as $row) {
      $collations[$row->Field] = $row->Collation;
    }
    // Unfortunately, it's not consistent whether the database will report the
    // collation as "utf8_general_ci" or "utf8mb3_general_ci".
    $this->assertContains($collations['item_id'], ['utf8mb3_general_ci', 'utf8_general_ci']);
    $this->assertEquals('utf8mb4_bin', $collations['word']);
  }

  /**
   * Tests that bigram indexing doesn't choke on 49-characters words.
   *
   * @see https://www.drupal.org/node/3397017
   */
  protected function regressionTest3397017(): void {
    // Index all items before adding a new one, so we can better predict the
    // expected count.
    $this->indexItems($this->indexId);

    $entity_id = count($this->entities) + 1;
    // @see \Drupal\search_api_db\Plugin\search_api\backend\Database::TOKEN_LENGTH_MAX
    $long_word = str_repeat('a', 49);
    $entity = $this->addTestEntity($entity_id, [
      'type' => 'article',
      'body' => "foo $long_word bar baz",
    ]);

    $count = $this->indexItems($this->indexId);
    $this->assertEquals(1, $count);
    $results = $this->buildSearch($long_word)
      ->execute();
    $this->assertResults([$entity_id], $results, 'String filter with trailing space');

    $entity->delete();
    unset($this->entities[$entity_id]);
  }

  /**
   * Tests that AND facets work correctly with single-field partial matching.
   *
   * @see https://www.drupal.org/node/3436123
   */
  protected function regressionTest3436123(): void {
    $this->assertEquals('words', $this->getServer()->getBackendConfig()['matching']);
    $this->setServerMatchMode();

    $query = $this->buildSearch('test', ['type,item']);
    $query->setFulltextFields(['body']);
    $facets['category'] = [
      'field' => 'category',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
      'operator' => 'and',
    ];
    $query->setOption('search_api_facets', $facets);
    $results = $query->execute();
    $this->assertResults([1, 2, 3], $results, 'AND facets query');
    $expected = [
      ['count' => 2, 'filter' => '"item_category"'],
      ['count' => 1, 'filter' => '!'],
    ];
    $category_facets = $results->getExtraData('search_api_facets')['category'];
    usort($category_facets, [$this, 'facetCompare']);
    $this->assertEquals($expected, $category_facets, 'Incorrect AND facets were returned');

    $this->setServerMatchMode('words');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkIndexWithoutFields() {
    $index = parent::checkIndexWithoutFields();

    $expected = [
      'search_api_datasource',
      'search_api_language',
    ];
    $db_info = $this->getIndexDbInfo($index->id());
    $info_fields = array_keys($db_info['field_tables']);
    sort($info_fields);
    $this->assertEquals($expected, $info_fields);

    return $index;
  }

  /**
   * {@inheritdoc}
   */
  protected function regressionTest2471509(): void {
    // As this test will log an exception, we need to take that into account.
    $this->logger->setExpectedErrors(2);
    parent::regressionTest2471509();
    $this->logger->assertAllExpectedErrorsEncountered();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkModuleUninstall() {
    $db_info = $this->getIndexDbInfo();
    $normalized_storage_table = $db_info['index_table'];
    $field_tables = $db_info['field_tables'];

    // See whether clearing the server works.
    // Regression test for #2156151.
    $server = $this->getServer();
    $index = $this->getIndex();
    $server->deleteAllIndexItems($index);
    $query = $this->buildSearch();
    $results = $query->execute();
    $this->assertEquals(0, $results->getResultCount(), 'Clearing the server worked correctly.');
    $schema = \Drupal::database()->schema();
    $table_exists = $schema->tableExists($normalized_storage_table);
    $this->assertTrue($table_exists, 'The index tables were left in place.');

    // See whether disabling the index correctly removes all of its tables.
    $index->disable()->save();
    $db_info = $this->getIndexDbInfo();
    $this->assertNull($db_info, 'The index was successfully removed from the server.');
    $table_exists = $schema->tableExists($normalized_storage_table);
    $this->assertFalse($table_exists, 'The index tables were deleted.');
    foreach ($field_tables as $field_table) {
      $table_exists = $schema->tableExists($field_table['table']);
      $this->assertFalse($table_exists, "Field table {$field_table['table']} was successfully deleted.");
    }
    $index->enable()->save();

    // Remove first the index and then the server.
    $index->setServer();
    $index->save();

    $db_info = $this->getIndexDbInfo();
    $this->assertNull($db_info, 'The index was successfully removed from the server.');
    $table_exists = $schema->tableExists($normalized_storage_table);
    $this->assertFalse($table_exists, 'The index tables were deleted.');
    foreach ($field_tables as $field_table) {
      $table_exists = $schema->tableExists($field_table['table']);
      $this->assertFalse($table_exists, "Field table {$field_table['table']} was successfully deleted.");
    }

    // Re-add the index to see if the associated tables are also properly
    // removed when the server is deleted.
    $index->setServer($server);
    $index->save();
    $server->delete();

    $db_info = $this->getIndexDbInfo();
    $this->assertNull($db_info, 'The index was successfully removed from the server.');
    $table_exists = $schema->tableExists($normalized_storage_table);
    $this->assertFalse($table_exists, 'The index tables were deleted.');
    foreach ($field_tables as $field_table) {
      $table_exists = $schema->tableExists($field_table['table']);
      $this->assertFalse($table_exists, "Field table {$field_table['table']} was successfully deleted.");
    }

    // Uninstall the module.
    \Drupal::service('module_installer')->uninstall(['search_api_db'], FALSE);
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('search_api_db'), 'The Database Search module was successfully uninstalled.');

    $tables = $schema->findTables('search_api_db_%');
    $expected = [
      'search_api_db_database_search_index' => 'search_api_db_database_search_index',
    ];
    $this->assertEquals($expected, $tables, 'All the tables of the Database Search module have been removed.');
  }

  /**
   * Retrieves the database information for the test index.
   *
   * @param string|null $index_id
   *   (optional) The ID of the index whose database information should be
   *   retrieved.
   *
   * @return array
   *   The database information stored by the backend for the test index.
   */
  protected function getIndexDbInfo($index_id = NULL) {
    $index_id = $index_id ?: $this->indexId;
    return \Drupal::keyValue(Database::INDEXES_KEY_VALUE_STORE_ID)
      ->get($index_id);
  }

  /**
   * Indexes an item directly.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index to index the item on.
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The item.
   *
   * @return string[]
   *   The successfully indexed IDs.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if indexing failed.
   */
  protected function indexItemDirectly(IndexInterface $index, ItemInterface $item) {
    $items = [$item->getId() => $item];

    // Minimalistic version of code copied from
    // \Drupal\search_api\Entity\Index::indexSpecificItems().
    $index->alterIndexedItems($items);
    \Drupal::moduleHandler()->alter('search_api_index_items', $index, $items);
    $event = new IndexingItemsEvent($index, $items);
    \Drupal::getContainer()->get('event_dispatcher')
      ->dispatch($event, SearchApiEvents::INDEXING_ITEMS);
    foreach ($items as $item) {
      // This will cache the extracted fields so processors, etc., can retrieve
      // them directly.
      $item->getFields();
    }
    $index->preprocessIndexItems($items);

    $indexed_ids = [];
    if ($items) {
      $indexed_ids = $index->getServerInstance()->indexItems($index, $items);
    }
    return $indexed_ids;
  }

  /**
   * Tests whether a server on a non-default database is handled correctly.
   */
  public function testNonDefaultDatabase() {
    // Clone the primary credentials to a replica connection.
    // Note this will result in two independent connection objects that happen
    // to point to the same place.
    // @see \Drupal\KernelTests\Core\Database\ConnectionTest::testConnectionRouting()
    $connection_info = CoreDatabase::getConnectionInfo('default');
    CoreDatabase::addConnectionInfo('default', 'replica', $connection_info['default']);

    $db1 = CoreDatabase::getConnection('default', 'default');
    $db2 = CoreDatabase::getConnection('replica', 'default');

    // Safety checks copied from the Core test, if these fail something is wrong
    // with Core.
    $this->assertNotNull($db1, 'default connection is a real connection object.');
    $this->assertNotNull($db2, 'replica connection is a real connection object.');
    $this->assertNotSame($db1, $db2, 'Each target refers to a different connection.');

    // Create backends based on each of the two targets and verify they use the
    // right connections.
    $config = [
      'database' => 'default:default',
    ];
    $backend1 = Database::create($this->container, $config, '', []);
    $config['database'] = 'default:replica';
    $backend2 = Database::create($this->container, $config, '', []);

    $this->assertSame($db1, $backend1->getDatabase());
    $this->assertSame($db2, $backend2->getDatabase());

    // Make sure they also use different DBMS compatibility handlers, which also
    // use the correct database connections.
    $dbms_comp1 = $backend1->getDbmsCompatibilityHandler();
    $dbms_comp2 = $backend2->getDbmsCompatibilityHandler();
    $this->assertNotSame($dbms_comp1, $dbms_comp2);
    $this->assertSame($db1, $dbms_comp1->getDatabase());
    $this->assertSame($db2, $dbms_comp2->getDatabase());

    // Finally, make sure the DBMS compatibility handlers also have the correct
    // classes (meaning we used the correct one and didn't just fall back to the
    // generic database).
    $service = $this->container->get('search_api_db.database_compatibility');
    $database_type = $db1->databaseType();
    $service_id = "$database_type.search_api_db.database_compatibility";
    $service2 = $this->container->get($service_id);
    $this->assertSame($service2, $service);
    $class = get_class($service);
    $this->assertNotEquals(GenericDatabase::class, $class);
    $this->assertSame($dbms_comp1, $service);
    $this->assertEquals($class, get_class($dbms_comp2));
  }

  /**
   * Tests whether indexing of dates works correctly.
   */
  public function testDateIndexing() {
    // Load all existing entities.
    $storage = \Drupal::entityTypeManager()
      ->getStorage('entity_test_mulrev_changed');
    $storage->delete($storage->loadMultiple());

    $index = Index::load('database_search_index');
    $index->getField('name')->setType('date');
    $index->save();

    // Simulate date field creation in one timezone and indexing in another.
    date_default_timezone_set('America/Chicago');

    // Test different input values, similar to @dataProvider (but with less
    // overhead).
    $t = 1400000000;
    $date_time_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $date_format = DateTimeItemInterface::DATE_STORAGE_FORMAT;
    $test_values = [
      'null' => [NULL, NULL],
      'timestamp' => [$t, $t],
      'string timestamp' => ["$t", $t],
      'float timestamp' => [$t + 0.12, $t],
      'date string' => [gmdate($date_time_format, $t), $t],
      'date string with timezone' => [date($date_time_format . 'P', $t), $t],
      'date only' => [
        date($date_format, $t),
        // Date-only fields are stored with the default time (12:00:00).
        strtotime(date($date_format, $t) . 'T12:00:00+00:00'),
      ],
    ];

    // Get storage information for quickly checking the indexed value.
    $db_info = $this->getIndexDbInfo();
    $table = $db_info['index_table'];
    $column = $db_info['field_tables']['name']['column'];
    $sql = "SELECT [$column] FROM {{$table}} WHERE [item_id] = :id";

    $id = 0;
    date_default_timezone_set('Asia/Seoul');
    foreach ($test_values as $label => [$field_value, $expected]) {
      $entity = $this->addTestEntity(++$id, [
        'name' => $field_value,
        'type' => 'item',
      ]);
      $item_id = $this->getItemIds([$id])[0];
      $index->indexSpecificItems([$item_id => $entity->getTypedData()]);
      $args[':id'] = $item_id;
      $indexed_value = \Drupal::database()->query($sql, $args)->fetchField();
      if ($expected === NULL) {
        $this->assertSame($expected, $indexed_value, "Indexing of date field with $label value.");
      }
      else {
        $this->assertEquals($expected, $indexed_value, "Indexing of date field with $label value.");
      }
    }
  }

  /**
   * Tests negated fulltext searches with substring matching.
   *
   * @param string $match_mode
   *   The match mode to use – "partial", "prefix" or "words".
   *
   * @see https://www.drupal.org/project/search_api/issues/2949962
   *
   * @dataProvider matchModesDataProvider
   */
  public function testRegression2949962(string $match_mode): void {
    $this->insertExampleContent();
    $this->setServerMatchMode($match_mode);
    $this->indexItems($this->indexId);

    $searches = [
      'not this word' => [
        'keys' => [
          '#conjunction' => 'OR',
          '#negation' => TRUE,
          'test',
        ],
        'expected_results' => [
          1,
          3,
          4,
          5,
        ],
      ],
      'none of these words' => [
        'keys' => [
          '#conjunction' => 'OR',
          '#negation' => TRUE,
          'test',
          'foo',
        ],
        'expected_results' => [
          3,
          5,
        ],
      ],
      'not all of these words' => [
        'keys' => [
          '#conjunction' => 'AND',
          '#negation' => TRUE,
          'foo',
          'baz',
        ],
        'expected_results' => [
          2,
          3,
          5,
        ],
      ],
      'complex keywords' => [
        'keys' => [
          [
            'foo',
            'bar',
            '#conjunction' => 'AND',
          ],
          [
            'test',
            '#conjunction' => 'OR',
            '#negation' => TRUE,
          ],
          '#conjunction' => 'AND',
        ],
        'expected_results' => [
          1,
        ],
      ],
    ];

    foreach ($searches as $search) {
      $results = $this->buildSearch($search['keys'], [], ['name'])->execute();
      $this->assertResults($search['expected_results'], $results);
    }
  }

  /**
   * Provides test data for test methods that need to test all match modes.
   *
   * @return array
   *   An associative array of argument arrays for test methods that take any
   *   match mode as an argument.
   */
  public static function matchModesDataProvider(): array {
    return [
      'Match mode "partial"' => ['partial'],
      'Match mode "prefix"' => ['prefix'],
      'Match mode "words"' => ['words'],
    ];
  }

  /**
   * Tests whether cleanNumericString() works correctly.
   */
  public function testCleanNumericString() {
    $class = new \ReflectionClass(Database::class);
    /** @see \Drupal\search_api_db\Plugin\search_api\backend\Database::cleanNumericString() */
    $method = $class->getMethod('cleanNumericString');

    $this->assertEquals('42', $method->invoke(NULL, '-042'));
    $this->assertEquals('42', $method->invoke(NULL, '00042'));
    $this->assertEquals('0', $method->invoke(NULL, '0'));
    $this->assertEquals('0', $method->invoke(NULL, '000'));
    $this->assertEquals('0', $method->invoke(NULL, '-0'));
  }

  /**
   * Test AND of nested OR groups, and negated nested group queries.
   *
   * @param string $match_mode
   *   The match mode to use – "partial", "prefix" or "words".
   *
   * @see https://www.drupal.org/node/3537045
   *
   * @dataProvider matchModesDataProvider
   */
  public function testRegression3537045(string $match_mode): void {
    $this->addTestEntity(1, [
      'name' => 'foo baz',
      'body' => 'bar fux',
      'type' => 'article',
    ]);

    $this->addTestEntity(2, [
      'name' => 'bar',
      'body' => 'Nothing else mentioned here.',
      'type' => 'article',
    ]);

    $this->addTestEntity(3, [
      'name' => 'Nothing else mentioned here.',
      'body' => 'fux',
      'type' => 'article',
    ]);

    $this->addTestEntity(4, [
      'name' => 'foo baz qux quux',
      'body' => 'Nothing else mentioned here.',
      'type' => 'article',
    ]);

    $this->addTestEntity(5, [
      'name' => 'foo qux quux',
      'body' => 'Nothing else mentioned here.',
      'type' => 'article',
    ]);

    $count = \Drupal::entityQuery('entity_test_mulrev_changed')
      ->count()
      ->accessCheck(FALSE)
      ->execute();
    $this->assertEquals(5, $count, "$count items inserted.");

    $this->setServerMatchMode($match_mode);
    $this->indexItems($this->indexId);

    $searches = [
      // (foo OR bar) AND (baz OR fux)
      // Count rows combined by UNION (one per matched group) to avoid counting
      // non-existing [t].[word].
      'grouped AND condition' => [
        'keys' => [
          '#conjunction' => 'AND',
          [
            '#conjunction' => 'OR',
            'foo',
            'bar',
          ],
          [
            '#conjunction' => 'OR',
            'baz',
            'fux',
          ],
        ],
        'expected_results' => [
          1,
          4,
        ],
      ],
      // NOT (foo AND (bar OR baz))
      // Ensure negated word queries select consistent fields preventing UNION
      // column mismatches.
      'negation with nested groups and phrases' => [
        'keys' => [
          '#negation' => TRUE,
          '#conjunction' => 'AND',
          [
            '#conjunction' => 'AND',
            'foo',
            [
              '#conjunction' => 'OR',
              'bar',
              'baz',
            ],
          ],
        ],
        'expected_results' => [
          2,
          3,
          5,
        ],
      ],
      // foo AND (NOT baz OR (baz NOT qux))
      // Ensures every NOT IN (...) subquery returns exactly one column
      // (item_id).
      'single-column NOT IN subqueries' => [
        'keys' => [
          '#conjunction' => 'AND',
          'foo',
          [
            '#conjunction' => 'OR',
            [
              '#negation' => TRUE,
              '#conjunction' => 'AND',
              'baz',
            ],
            [
              '#conjunction' => 'AND',
              'baz',
              [
                '#negation' => TRUE,
                '#conjunction' => 'AND',
                'qux',
              ],
            ],
          ],
        ],
        'expected_results' => [
          1,
          5,
        ],
      ],

      // foo AND (NOT bar OR (baz NOT "qux quux"))
      // Verifies that the positive nested branch is preserved (OR baz)
      // meaning no unintended NOT IN negation happens as in:
      // foo AND (NOT bar OR (NOT baz OR qux))
      'positive nested branch preserved' => [
        'keys' => [
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
        'expected_results' => [
          1,
          4,
          5,
        ],
      ],

      // foo AND (NOT bar OR NOT (baz NOT "qux quux"))
      // Guard against the De Morgan drift.
      // NOT (baz NOT "qux quux") == (NOT baz) OR "qux quux"
      'guard against de morgan drift' => [
        'keys' => [
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
              '#negation' => TRUE,
              '#conjunction' => 'AND',
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
        ],
        'expected_results' => [
          4,
          5,
        ],
      ],
      // foo AND (baz NOT "qux quux")
      // Isolates the (baz NOT "qux quux") branch
      'baz branch isolated' => [
        'keys' => [
          '#conjunction' => 'AND',
          'foo',
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
        'expected_results' => [
          1,
        ],
      ],
    ];

    foreach ($searches as $search) {
      $results = $this->buildSearch($search['keys'], [], ['name', 'body'])->execute();
      $this->assertResults($search['expected_results'], $results);
    }
  }

}
