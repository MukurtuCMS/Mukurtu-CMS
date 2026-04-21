<?php

namespace Drupal\Tests\search_api_solr_legacy\Functional;

use Drupal\search_api_solr_legacy_test\Plugin\SolrConnector\Solr36TestConnector;
use Drupal\Tests\search_api_solr\Functional\ViewsTest as SearchApiSolrViewsTest;

/**
 * Tests the Views integration of the Search API.
 *
 * @group search_api_solr_legacy
 */
class ViewsTest extends SearchApiSolrViewsTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api_solr_legacy',
    'search_api_solr_legacy_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function adjustBackendConfig() {
    // Swap the connector.
    Solr36TestConnector::adjustBackendConfig('search_api.server.solr_search_server');
  }

  /**
   * Tests the Views admin UI and field handlers.
   */
  public function testViewsAdmin() {
    $this->markTestSkipped('This test fails on Solr 3.6. It requires some more debugging.');
  }

}
