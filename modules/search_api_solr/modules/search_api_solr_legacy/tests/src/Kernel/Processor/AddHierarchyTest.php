<?php

namespace Drupal\Tests\search_api_solr_legacy\Kernel\Processor;

use Drupal\search_api_solr_legacy_test\Plugin\SolrConnector\Solr36TestConnector;
use Drupal\Tests\search_api_solr\Kernel\Processor\AddHierarchyTest as SearchApiSolrAddHierarchyTest;

/**
 * Tests the "Hierarchy" processor.
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\AddHierarchy
 *
 * @group search_api_solr_legacy
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\processor\AddHierarchy
 */
class AddHierarchyTest extends SearchApiSolrAddHierarchyTest {

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
  protected function enableSolrServer() {
    parent::enableSolrServer();

    // Swap the connector.
    Solr36TestConnector::adjustBackendConfig('search_api.server.solr_search_server');
  }

}
