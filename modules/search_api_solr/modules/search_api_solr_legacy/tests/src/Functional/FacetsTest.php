<?php

namespace Drupal\Tests\search_api_solr_legacy\Functional;

use Drupal\search_api_solr_legacy_test\Plugin\SolrConnector\Solr36TestConnector;
use Drupal\Tests\search_api_solr\Functional\FacetsTest as SearchApiSolrFacetsTest;

/**
 * Tests the facets functionality using the Solr backend.
 *
 * @group search_api_solr_legacy
 */
class FacetsTest extends SearchApiSolrFacetsTest {

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
  public function setUp(): void {
    parent::setUp();

    // Swap the connector.
    Solr36TestConnector::adjustBackendConfig('search_api.server.solr_search_server');
  }

}
