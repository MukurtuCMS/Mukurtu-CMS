<?php

namespace Drupal\search_api_solr;

/**
 * Provides an interface defining a SolrRequestHandler entity.
 */
interface SolrRequestHandlerInterface extends EnvironmentAwareSolrConfigInterface {

  /**
   * Gets the Solr RequestHandler definition as nested associative array.
   *
   * @return array
   *   The Solr RequestHandler definition as nested associative array.
   */
  public function getRequestHandler();

}
