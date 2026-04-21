<?php

namespace Drupal\Tests\search_api_solr_legacy\Kernel;

use Drupal\search_api_solr_legacy_test\Plugin\SolrConnector\Solr36TestConnector;
use Drupal\Tests\search_api_solr\Kernel\SearchApiSolrExtractionTest;

/**
 * Test tika extension based PDF extraction.
 *
 * @group search_api_solr_legacy
 */
class SolrLegacyExtractionTest extends SearchApiSolrExtractionTest {

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
  protected function installConfigs() {
    parent::installConfigs();

    $this->installConfig([
      'search_api_solr_legacy',
      'search_api_solr_legacy_test',
    ]);

    // Swap the connector.
    Solr36TestConnector::adjustBackendConfig('search_api.server.solr_search_server');
  }

}
