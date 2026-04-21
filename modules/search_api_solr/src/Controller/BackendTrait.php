<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SolrBackendInterface;

/**
 * Provides a listing of Solr Entities.
 */
trait BackendTrait {

  /**
   * The Search API server backend.
   *
   * @var \Drupal\search_api_solr\SolrBackendInterface
   */
  protected $backend;

  /**
   * The Search API server ID.
   *
   * @var string
   */
  protected $serverId = '';

  /**
   * The Solr minimum version string.
   *
   * @var string
   */
  protected $assumedMinimumVersion = '';

  /**
   * Reset.
   *
   * @var bool
   */
  protected $reset = FALSE;

  /**
   * Sets the Search API server and calls setBackend() afterward.
   *
   * @param \Drupal\search_api\ServerInterface $server
   *   The Search API server entity.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function setServer(ServerInterface $server) {
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = $server->getBackend();
    $this->setBackend($backend);
    $this->serverId = $server->id();
  }

  /**
   * Sets the Search API server backend.
   *
   * @param \Drupal\search_api_solr\SolrBackendInterface $backend
   *   The Search API server backend.
   */
  public function setBackend(SolrBackendInterface $backend) {
    $this->backend = $backend;
    $this->reset = TRUE;
  }

  /**
   * Returns the Search API server backend.
   *
   * @return \Drupal\search_api_solr\SolrBackendInterface|null
   *   The Search API server backend.
   */
  protected function getBackend() {
    return $this->backend;
  }

  /**
   * Set assumed minimum version.
   *
   * @param string $assumedMinimumVersion
   *   Assumed minimum version.
   */
  public function setAssumedMinimumVersion(string $assumedMinimumVersion) {
    $this->assumedMinimumVersion = $assumedMinimumVersion;
  }

}
