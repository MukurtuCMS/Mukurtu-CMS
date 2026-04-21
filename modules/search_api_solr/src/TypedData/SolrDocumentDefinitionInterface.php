<?php

namespace Drupal\search_api_solr\TypedData;

use Drupal\Core\TypedData\ComplexDataDefinitionInterface;

/**
 * Interface for typed data Solr document definitions.
 */
interface SolrDocumentDefinitionInterface extends ComplexDataDefinitionInterface {

  /**
   * Gets the Search API Index ID.
   *
   * @return string|null
   *   The Index ID, or NULL if the Index is unknown.
   */
  public function getIndexId();

  /**
   * Sets the Search API Index ID.
   *
   * @param string $index_id
   *   The Server ID to set.
   *
   * @return $this
   */
  public function setIndexId(string $index_id);

}
