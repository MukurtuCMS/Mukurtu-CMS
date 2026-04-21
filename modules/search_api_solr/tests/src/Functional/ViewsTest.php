<?php

namespace Drupal\Tests\search_api_solr\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api_solr\Utility\SolrCommitTrait;
use Drupal\Tests\search_api\Functional\SearchApiBrowserTestBase;
use Drupal\Tests\search_api\Functional\ViewsTest as SearchApiViewsTest;

/**
 * Tests the Views integration of the Search API.
 *
 * @group search_api_solr
 */
class ViewsTest extends SearchApiViewsTest {

  use SolrCommitTrait;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  protected static $modules = ['search_api_solr_test'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    // Skip parent::setUp() to use Solr iunstead of the DB backend!
    SearchApiBrowserTestBase::setUp();

    // Add a second language.
    ConfigurableLanguage::createFromLangcode('nl')->save();

    // Swap database backend for Solr backend.
    $config_factory = \Drupal::configFactory();
    $config_factory->getEditable('search_api.index.database_search_index')
      ->delete();
    $config_factory->rename('search_api.index.solr_search_index', 'search_api.index.database_search_index');
    $config_factory->getEditable('search_api.index.database_search_index')
      ->set('id', 'database_search_index')
      ->save();

    $this->adjustBackendConfig();

    // Now do the same as parent::setUp().
    \Drupal::getContainer()
      ->get('search_api.index_task_manager')
      ->addItemsAll(Index::load($this->indexId));
    $this->insertExampleContent();
    $this->indexItems($this->indexId);

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (!Utility::isRunningInCli()) {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }

    $this->rebuildContainer();
  }

  /**
   * Allow 3rd party Solr connectors to manipulate the config.
   */
  protected function adjustBackendConfig() {}

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $index = Index::load($this->indexId);
    $index->clear();
    $this->ensureCommit($index);
    parent::tearDown();
  }

  /**
   * {@inheritdoc}
   */
  public function testSearchView() {
    // @see https://www.drupal.org/node/2773019
    $query = ['language' => ['***LANGUAGE_language_interface***']];
    $this->checkResults($query, [1, 2, 3, 4, 5], 'Search with interface language as filter');

    parent::testSearchView();
  }

  /**
   * Indexes all (unindexed) items on the specified index.
   *
   * @param string $index_id
   *   The ID of the index on which items should be indexed.
   *
   * @return int
   *   The number of successfully indexed items.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function indexItems($index_id) {
    $index_status = parent::indexItems($index_id);
    $index = Index::load($index_id);
    $this->ensureCommit($index);
    return $index_status;
  }

}
