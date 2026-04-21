<?php

namespace Drupal\Tests\search_api_solr\Kernel\Processor;

use Drupal\search_api\Entity\Server;
use Drupal\search_api_solr\Utility\SolrCommitTrait;
use Symfony\Component\Yaml\Yaml;

defined('SOLR_CLOUD') || define('SOLR_CLOUD', getenv('SOLR_CLOUD') ?: 'false');

/**
 * Helper to exchange the DB backend for a Solr backend in processor tests.
 */
trait SolrBackendTrait {

  use SolrCommitTrait;

  /**
   * Swap the DB backend for a Solr backend.
   *
   * This function has to be called from the test setUp() function.
   */
  protected function enableSolrServer() {
    $config = '/config/install/search_api.server.solr_search_server' . ('true' === SOLR_CLOUD ? '_cloud' : '') . '.yml';
    $this->server = Server::create(
      Yaml::parse(file_get_contents(
        \Drupal::service('extension.list.module')->getPath('search_api_solr_test') . $config
      ))
    );
    $this->server->save();

    $this->index->setServer($this->server);
    $this->index->save();

    $index_storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('search_api_index');
    $index_storage->resetCache([$this->index->id()]);
    $this->index = $index_storage->load($this->index->id());
  }

  /**
   * {@inheritdoc}
   */
  protected function indexItems() {
    $index_status = parent::indexItems();
    $this->ensureCommit($this->index);
    return $index_status;
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->index->clear();
    $this->ensureCommit($this->index);
    parent::tearDown();
  }

}
