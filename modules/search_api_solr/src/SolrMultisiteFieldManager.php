<?php

namespace Drupal\search_api_solr;

use Drupal\search_api\IndexInterface;
use Drupal\search_api_solr\TypedData\SolrMultisiteFieldDefinition;

/**
 * Manages the discovery of Solr fields.
 */
class SolrMultisiteFieldManager extends SolrFieldManager {

  /**
   * Builds the field definitions for a multisite index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index from which we are retrieving field information.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   The array of field definitions for the server, keyed by field name.
   */
  protected function buildFieldDefinitions(IndexInterface $index) {
    $fields = [];
    foreach ($index->getFields() as $index_field) {
      $solr_field = $index_field->getPropertyPath();
      $field = new SolrMultisiteFieldDefinition(['multivalued' => preg_match('/^[a-z]+m_/', $solr_field)]);
      $field->setLabel($index_field->getLabel());
      $field->setDataType($index_field->getType());
      $fields[$solr_field] = $field;
    }
    return $fields;
  }

}
