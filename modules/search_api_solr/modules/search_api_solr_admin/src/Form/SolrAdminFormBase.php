<?php

namespace Drupal\search_api_solr_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api_solr_admin\Utility\SolrAdminCommandHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form for Solr admin forms.
 *
 * @package Drupal\search_api_solr_admin\Form
 */
abstract class SolrAdminFormBase extends FormBase {

  use LoggerTrait {
    getLogger as getSearchApiLogger;
  }

  /**
   * The core messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The Search API server entity.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $searchApiServer;

  /**
   * The Search API server entity.
   *
   * @var \Drupal\search_api_solr_admin\Utility\SolrAdminCommandHelper
   */
  protected $commandHelper;

  /**
   * SolrDeleteCollectionForm constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\search_api_solr_admin\Utility\SolrAdminCommandHelper $commandHelper
   *   The command helper.
   */
  public function __construct(MessengerInterface $messenger, SolrAdminCommandHelper $commandHelper) {
    $this->messenger = $messenger;
    $this->commandHelper = $commandHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('search_api_solr_admin.command_helper')
    );
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
