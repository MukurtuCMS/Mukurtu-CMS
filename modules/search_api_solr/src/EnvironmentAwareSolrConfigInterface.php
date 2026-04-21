<?php

namespace Drupal\search_api_solr;

/**
 * Provides an interface defining a SolrCache entity.
 */
interface EnvironmentAwareSolrConfigInterface extends SolrConfigInterface {

  /**
   * Gets the environments targeted by this Solr Config.
   *
   * @return string[]
   *   Environments.
   */
  public function getEnvironments();

}
