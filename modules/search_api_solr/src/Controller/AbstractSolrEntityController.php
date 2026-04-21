<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SolrConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides different listings of Solr Entities.
 */
abstract class AbstractSolrEntityController extends ControllerBase {

  use LoggerTrait {
    getLogger as getSearchApiLogger;
  }

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Entity type id.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  /**
   * Constructs a SolrRequestHandlerController object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * Provides the listing page.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return array
   *   A render array as expected by drupal_render().
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function listing(ServerInterface $search_api_server) {
    return $this->getListBuilder($search_api_server)->render();
  }

  /**
   * Gets the list builder.
   *
   * Ensures that the list builder uses the correct Solr backend.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return \Drupal\search_api_solr\Controller\AbstractSolrEntityListBuilder
   *   The SolrRequestHandler list builder object.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getListBuilder(ServerInterface $search_api_server) {
    /** @var \Drupal\search_api_solr\Controller\AbstractSolrEntityListBuilder $list_builder */
    $list_builder = $this->entityTypeManager()->getListBuilder($this->entityTypeId);
    $list_builder->setServer($search_api_server);
    return $list_builder;
  }

  /**
   * Disables a Solr Entity on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   Search API server.
   * @param \Drupal\search_api_solr\SolrConfigInterface $solr_entity
   *   Solr entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function doDisableOnServer(ServerInterface $search_api_server, SolrConfigInterface $solr_entity): RedirectResponse {
    $disabled_key = $solr_entity->getEntityType()->getKey('disabled');
    $backend_config = $search_api_server->getBackendConfig();
    $backend_config[$disabled_key][] = $solr_entity->id();
    $backend_config[$disabled_key] = array_unique($backend_config[$disabled_key]);
    $search_api_server->setBackendConfig($backend_config);
    $search_api_server->save();
    return new RedirectResponse($solr_entity->toUrl('collection')->toString());
  }

  /**
   * Enables a Solr Entity on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   Search API server.
   * @param \Drupal\search_api_solr\SolrConfigInterface $solr_entity
   *   Solr entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function doEnableOnServer(ServerInterface $search_api_server, SolrConfigInterface $solr_entity): RedirectResponse {
    $disabled_key = $solr_entity->getEntityType()->getKey('disabled');
    $backend_config = $search_api_server->getBackendConfig();
    $backend_config[$disabled_key] = array_values(array_diff($backend_config[$disabled_key], [$solr_entity->id()]));
    $search_api_server->setBackendConfig($backend_config);
    $search_api_server->save();
    return new RedirectResponse($solr_entity->toUrl('collection')->toString());
  }

  /**
   * Get Logger.
   *
   * @param string $channel
   *   The log channel.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger.
   */
  protected function getLogger($channel = '') {
    return $this->getSearchApiLogger();
  }

}
