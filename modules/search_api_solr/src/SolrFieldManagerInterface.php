<?php

namespace Drupal\search_api_solr;

use Drupal\search_api\IndexInterface;

/**
 * Defines an interface for a Solr field manager.
 */
interface SolrFieldManagerInterface {

  /**
   * Gets the field definitions for a Solr server.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search Api index.
   *
   * @return \Drupal\search_api_solr\TypedData\SolrFieldDefinitionInterface[]
   *   The array of field definitions for the server, keyed by field name.
   */
  public function getFieldDefinitions(IndexInterface $index);

}
