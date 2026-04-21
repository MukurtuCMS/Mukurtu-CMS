<?php

namespace Drupal\Tests\search_api_solr\Kernel;

/**
 * Tests the document datasources using the solr techproducts example.
 *
 * @group search_api_solr
 */
class SearchApiSolrTechproductsTest extends SolrBackendTestBase {

  /**
   * A Search API server ID.
   *
   * @var string
   */
  protected $serverId = 'techproducts';

  /**
   * A Search API index ID.
   *
   * @var string
   */
  protected $indexId = 'techproducts';

  /**
   * {@inheritdoc}
   */
  protected function getItemIds(array $result_ids) {
    return $result_ids;
  }

  /**
   * Tests location searches and distance facets.
   */
  public function testBackend() {
    try {
      $this->firstSearch();
    }
    catch (\Exception $e) {
      $this->markTestSkipped('Techproducts example not reachable.');
    }

    $server = $this->getIndex()->getServerInstance();
    $config = $server->getBackendConfig();

    // Test processor based highlighting.
    $query = $this->buildSearch('Technology', [], ['manu']);
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »Technology« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $this->assertStringContainsString('<strong>Technology</strong>', (string) $result->getExtraData('highlighted_fields', ['manu' => ['']])['manu'][0]);
      $this->assertEmpty($result->getExtraData('highlighted_keys', []));
      $this->assertEquals('… A-DATA <strong>Technology</strong> Inc. …', $result->getExcerpt());
    }

    // Test server based highlighting.
    $config['highlight_data'] = TRUE;
    $server->setBackendConfig($config);
    $server->save();

    $query = $this->buildSearch('Technology', [], ['manu']);
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for »Technology« returned correct number of results.');
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $this->assertStringContainsString('<strong>Technology</strong>', (string) $result->getExtraData('highlighted_fields', ['manu' => ['']])['manu'][0]);
      $this->assertEquals(['Technology'], $result->getExtraData('highlighted_keys', []));
      $this->assertEquals('… A-DATA <strong>Technology</strong> Inc. …', $result->getExcerpt());
    }

    // Techproducts is read only, the data should not be deleted on index
    // removal. Regression test for
    // https://www.drupal.org/project/search_api_solr/issues/2847092
    $server->removeIndex($this->getIndex());
    $this->ensureCommit($this->getIndex());
    $server->addIndex($this->getIndex());
    $this->firstSearch();

    // Regression test for
    // https://www.drupal.org/project/search_api_solr/issues/3068714
    $config['rows'] = 2;
    $server->setBackendConfig($config);
    $server->save();
    /** @var \Drupal\search_api\Query\ResultSet $result */
    $query = $this->buildSearch(NULL, [], NULL, FALSE)
      ->sort('search_api_id');
    $query->range(0);
    $result = $query->execute();
    $this->assertEquals([
      "solr_document/0579B002",
      "solr_document/100-435805",
    ], array_keys($result->getResultItems()), 'Search for all tech products, 2 rows limit via config');
    $query = $this->buildSearch(NULL, [], NULL, FALSE)
      ->sort('search_api_id');
    $query->range(0, 3);
    $result = $query->execute();
    $this->assertEquals([
      "solr_document/0579B002",
      "solr_document/100-435805",
      "solr_document/3007WFP",
    ], array_keys($result->getResultItems()), 'Search for all tech products, 3 rows limit via query');
  }

  /**
   * Tests streaming expressions.
   *
   * @group not_solr3
   * @group not_solr4
   * @group not_solr5
   */
  public function testStreamingExpressions() {
    if ('false' === SOLR_CLOUD) {
      $this->markTestSkipped('This test requires a Solr Cloud setup.');
    }

    try {
      $this->firstSearch();
    }
    catch (\Exception $e) {
      $this->markTestSkipped('Techproducts example not reachable.');
    }

    $index = $this->getIndex();

    /** @var \Drupal\search_api_solr\Utility\StreamingExpressionQueryHelper $queryHelper */
    $queryHelper = \Drupal::service('search_api_solr.streaming_expression_query_helper');
    $query = $queryHelper->createQuery($index);
    $exp = $queryHelper->getStreamingExpressionBuilder($query);

    // The number of documents is not the same in different Solr versions, 32 or
    // 31.
    $this->assertGreaterThanOrEqual(31, $exp->getSearchAllRows());

    $search_expression = $exp->_search_all(
      'q="*:*"',
      'fl="' . $exp->_field('search_api_id') . '"',
      'sort="' . $exp->_field('search_api_id') . ' asc"'
    );

    $queryHelper->setStreamingExpression($query, $search_expression);
    $results = $query->execute();
    $this->assertGreaterThanOrEqual(31, $results->getResultCount());

    $topic_expression = $exp->_topic_all(
      $exp->_checkpoint('all_products'),
      'q="*:*"',
      'fl="' . $exp->_field('search_api_id') . '"'
    );

    $query = $queryHelper->createQuery($index);
    $queryHelper->setStreamingExpression($query, $topic_expression);
    $results = $query->execute();
    $this->assertGreaterThanOrEqual(31, $results->getResultCount());

    $query = $queryHelper->createQuery($index);
    $queryHelper->setStreamingExpression($query, $topic_expression);
    $results = $query->execute();
    $this->assertEquals(0, $results->getResultCount());

    $topic_expression = $exp->_topic(
      $exp->_checkpoint('20_products'),
      'q="*:*"',
      'fl="' . $exp->_field('search_api_id') . '"',
      'rows="20"'
    );
    $query = $queryHelper->createQuery($index);
    $queryHelper->setStreamingExpression($query, $topic_expression);
    $results = $query->execute();
    $this->assertEquals(20, $results->getResultCount());

    $query = $queryHelper->createQuery($index);
    $queryHelper->setStreamingExpression($query, $topic_expression);
    $results = $query->execute();
    $this->assertGreaterThanOrEqual(11, $results->getResultCount());

    $query = $queryHelper->createQuery($index);
    $queryHelper->setStreamingExpression($query, $topic_expression);
    $results = $query->execute();
    $this->assertEquals(0, $results->getResultCount());

    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = $index->getServerInstance()->getBackend();
    /** @var \Drupal\search_api_solr\SolrCloudConnectorInterface $connector */
    $connector = $backend->getSolrConnector();
    $connector->deleteCheckpoints($exp->_index_id(), $exp->_site_hash());

    $query = $queryHelper->createQuery($index);
    $queryHelper->setStreamingExpression($query, $topic_expression);
    $results = $query->execute();
    $this->assertEquals(20, $results->getResultCount());
  }

  /**
   * Executes a test search on the Solr server and assert the response data.
   */
  protected function firstSearch() {
    /** @var \Drupal\search_api\Query\ResultSet $result */
    $query = $this->buildSearch(NULL, [], NULL, FALSE)
      ->sort('search_api_id');
    $result = $query->execute();
    $this->assertEquals([
      "solr_document/0579B002",
      "solr_document/100-435805",
      "solr_document/3007WFP",
      "solr_document/6H500F0",
      "solr_document/9885A004",
      "solr_document/EN7800GTX/2DHTV/256M",
      "solr_document/EUR",
      "solr_document/F8V7067-APL-KIT",
      "solr_document/GB18030TEST",
      "solr_document/GBP",
    ], array_keys($result->getResultItems()), 'Search for all tech products');
  }

}
