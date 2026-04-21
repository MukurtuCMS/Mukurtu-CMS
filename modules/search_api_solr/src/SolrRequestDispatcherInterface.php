<?php

namespace Drupal\search_api_solr;

/**
 * Provides an interface defining a SolrRequestDispatcher entity.
 */
interface SolrRequestDispatcherInterface extends EnvironmentAwareSolrConfigInterface {

  /**
   * Gets the Solr RequestDispatcher definition as nested associative array.
   *
   * @return array
   *   The Solr RequestDispatcher definition as nested associative array.
   */
  public function getRequestDispatcher();

}
