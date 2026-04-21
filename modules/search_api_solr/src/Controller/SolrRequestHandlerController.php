<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SolrRequestHandlerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides different listings of SolrRequestHandler.
 */
class SolrRequestHandlerController extends AbstractSolrEntityController {

  /**
   * Entity type id.
   *
   * @var string
   */
  protected $entityTypeId = 'solr_request_handler';

  /**
   * Disables a Solr Request Handler on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   Search API server.
   * @param \Drupal\search_api_solr\SolrRequestHandlerInterface $solr_request_handler
   *   Solr entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function disableOnServer(ServerInterface $search_api_server, SolrRequestHandlerInterface $solr_request_handler): RedirectResponse {
    return $this->doDisableOnServer($search_api_server, $solr_request_handler);
  }

  /**
   * Enables a Solr Request Handler on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   Search API server.
   * @param \Drupal\search_api_solr\SolrRequestHandlerInterface $solr_request_handler
   *   Solr request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function enableOnServer(ServerInterface $search_api_server, SolrRequestHandlerInterface $solr_request_handler): RedirectResponse {
    return $this->doEnableOnServer($search_api_server, $solr_request_handler);
  }

}
