<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SolrRequestDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides different listings of SolrRequestDispatcher.
 */
class SolrRequestDispatcherController extends AbstractSolrEntityController {

  /**
   * Entity type id.
   *
   * @var string
   */
  protected $entityTypeId = 'solr_request_dispatcher';

  /**
   * Disables a Solr Request Dispatcher on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   Search API server.
   * @param \Drupal\search_api_solr\SolrRequestDispatcherInterface $solr_request_dispatcher
   *   Solr entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function disableOnServer(ServerInterface $search_api_server, SolrRequestDispatcherInterface $solr_request_dispatcher): RedirectResponse {
    return $this->doDisableOnServer($search_api_server, $solr_request_dispatcher);
  }

  /**
   * Enables a Solr Request Dispatcher on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   Search API server.
   * @param \Drupal\search_api_solr\SolrRequestDispatcherInterface $solr_request_dispatcher
   *   Solr request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function enableOnServer(ServerInterface $search_api_server, SolrRequestDispatcherInterface $solr_request_dispatcher): RedirectResponse {
    return $this->doEnableOnServer($search_api_server, $solr_request_dispatcher);
  }

}
