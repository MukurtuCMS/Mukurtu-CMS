<?php

namespace Drupal\search_api_solr\Plugin\SolrConnector;

use Drupal\search_api_solr\SolrConnector\BasicAuthTrait;

/**
 * Basic auth Solr connector.
 *
 * @SolrConnector(
 *   id = "solr_cloud_basic_auth",
 *   label = @Translation("Solr Cloud with Basic Auth"),
 *   description = @Translation("A connector usable for Solr Cloud installations protected by basic authentication.")
 * )
 */
class BasicAuthSolrCloudConnector extends StandardSolrCloudConnector {

  use BasicAuthTrait;

  /**
   * {@inheritdoc}
   */
  public function isTrustedContextSupported() {
    return TRUE;
  }

}
