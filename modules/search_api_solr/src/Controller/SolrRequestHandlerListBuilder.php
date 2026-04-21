<?php

namespace Drupal\search_api_solr\Controller;

/**
 * Provides a listing of SolrRequestHandler.
 */
class SolrRequestHandlerListBuilder extends AbstractSolrEntityListBuilder {

  /**
   * Request handler label.
   *
   * @var string
   */
  protected $label = 'Solr Request Handler';

  /**
   * Returns a list of all disabled request handlers for current server.
   *
   * @return array
   *   List of all disabled request handlers for current server.
   */
  protected function getDisabledEntities(): array {
    $backend = $this->getBackend();
    return $backend->getDisabledRequestHandlers();
  }

}
