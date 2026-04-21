<?php

namespace Drupal\search_api_solr\Plugin\SolrConnector;

use Drupal\search_api_solr\SolrConnector\BasicAuthTrait;

/**
 * Basic auth Solr connector.
 *
 * @SolrConnector(
 *   id = "basic_auth",
 *   label = @Translation("Basic Auth"),
 *   description = @Translation("A connector usable for Solr installations protected by basic authentication.")
 * )
 */
class BasicAuthSolrConnector extends StandardSolrConnector {

  use BasicAuthTrait;

}
