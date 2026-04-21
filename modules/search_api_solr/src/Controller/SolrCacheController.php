<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SolrCacheInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides different listings of SolrCache.
 */
class SolrCacheController extends AbstractSolrEntityController {

  /**
   * Entity type id.
   *
   * @var string
   */
  protected $entityTypeId = 'solr_cache';

  /**
   * Disables a Solr Cache on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   Search API server.
   * @param \Drupal\search_api_solr\SolrCacheInterface $solr_cache
   *   Solr entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function disableOnServer(ServerInterface $search_api_server, SolrCacheInterface $solr_cache): RedirectResponse {
    return $this->doDisableOnServer($search_api_server, $solr_cache);
  }

  /**
   * Enables a Solr Entity on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   Search API server.
   * @param \Drupal\search_api_solr\SolrCacheInterface $solr_cache
   *   Solr cache.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function enableOnServer(ServerInterface $search_api_server, SolrCacheInterface $solr_cache): RedirectResponse {
    return $this->doEnableOnServer($search_api_server, $solr_cache);
  }

}
