<?php

namespace Drupal\search_api_solr\Plugin\DataType;

/**
 * Defines the "Solr multisite field" data type.
 *
 * Instances of this class wrap Search API Field objects and allow to deal with
 * fields based upon the Typed Data API.
 *
 * @DataType(
 *   id = "solr_multisite_field",
 *   label = @Translation("Solr multisite field"),
 *   description = @Translation("Fields from a multisite Solr document."),
 *   definition_class = "\Drupal\search_api_solr\TypedData\SolrMultisiteFieldDefinition"
 * )
 */
class SolrMultisiteField extends SolrField {


  /**
   * Field name.
   *
   * @var string
   */
  protected static $solrField = 'solr_multisite_field';

}
