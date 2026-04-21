<?php

namespace Drupal\Tests\search_api_solr_legacy\Functional;

use Drupal\search_api_solr_legacy_test\Plugin\SolrConnector\Solr36TestConnector;
use Drupal\Tests\search_api_solr\Functional\IntegrationTest as SearchApiSolrIntegrationTest;

/**
 * Tests the overall functionality of the Search API framework and admin UI.
 *
 * @group search_api_solr_legacy
 */
class IntegrationTest extends SearchApiSolrIntegrationTest {

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

  /**
   * {@inheritdoc}
   */
  protected function configureBackendAndSave(array $edit) {
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Configure the selected backend.');

    $edit += [
      'backend_config[connector]' => 'solr_36',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Configure the selected Solr connector.');

    $edit += [
      'backend_config[connector_config][host]' => 'dummy',
    ];
    $this->submitForm($edit, 'Save');

    $this->assertSession()->pageTextContains('The server was successfully saved.');
    $this->assertSession()->addressEquals('admin/config/search/search-api/server/' . $this->serverId);
    $this->assertSession()->pageTextContains('The Solr server could not be reached or is protected by your service provider.');

    // Go back in and configure Solr.
    $edit_path = 'admin/config/search/search-api/server/' . $this->serverId . '/edit';
    $this->drupalGet($edit_path);
    $edit['backend_config[connector_config][host]'] = 'localhost';
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('The Solr server could be reached.');
  }

}
