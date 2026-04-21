<?php

namespace Drupal\search_api_solr_autocomplete\Plugin\search_api_autocomplete\suggester;

use Drupal\Core\Utility\Error;
use Drupal\search_api\IndexInterface;
use Drupal\search_api_solr\SolrBackendInterface;

/**
 * Provides a helper method for loading the search backend.
 */
trait BackendTrait {

  /**
   * Retrieves the backend for the given index, if it supports autocomplete.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   *
   * @return \Drupal\search_api_solr\SolrBackendInterface|null
   *   The backend plugin of the index's server, if it exists and supports
   *   autocomplete; NULL otherwise.
   */
  protected static function getBackend(IndexInterface $index): ?SolrBackendInterface {
    try {
      if (
        $index->hasValidServer() &&
        ($server = $index->getServerInstance()) &&
        ($backend = $server->getBackend()) &&
        $backend instanceof SolrBackendInterface &&
        $server->supportsFeature('search_api_autocomplete')
      ) {
        return $backend;
      }
    }
    catch (\Exception $e) {
      $logger = \Drupal::logger('logger.channel.search_api');
      Error::logException($logger, $e);
    }
    return NULL;
  }

}
