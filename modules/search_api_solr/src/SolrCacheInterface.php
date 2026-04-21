<?php

namespace Drupal\search_api_solr;

/**
 * Provides an interface defining a environment aware Solr entity.
 */
interface SolrCacheInterface extends EnvironmentAwareSolrConfigInterface {

  /**
   * Gets the Solr Cache definition as nested associative array.
   *
   * @return array
   *   The Solr Cache definition as nested associative array.
   */
  public function getCache();

}
