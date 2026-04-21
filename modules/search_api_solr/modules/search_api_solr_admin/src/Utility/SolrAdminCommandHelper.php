<?php

namespace Drupal\search_api_solr_admin\Utility;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\search_api_solr\Controller\SolrConfigSetController;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\Utility\SolrCommandHelper;
use Drupal\search_api_solr\Utility\Utility;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides functionality to be used by CLI tools.
 */
class SolrAdminCommandHelper extends SolrCommandHelper {

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a CommandHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\search_api_solr\Controller\SolrConfigSetController $configset_controller
   *   The configset controller.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the "search_api_index" or "search_api_server" entity types'
   *   storage handlers couldn't be loaded.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the "search_api_index" or "search_api_server" entity types are
   *   unknown.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, EventDispatcherInterface $event_dispatcher, SolrConfigSetController $configset_controller, FileSystemInterface $fileSystem, MessengerInterface $messenger) {
    parent::__construct($entity_type_manager, $module_handler, $event_dispatcher, $configset_controller);
    $this->fileSystem = $fileSystem;
    $this->messenger = $messenger;
  }

  /**
   * Reload Solr core or collection.
   *
   * @param string $server_id
   *   The ID of the server.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function reload(string $server_id): void {
    $server = $this->getServer($server_id);
    $connector = Utility::getSolrConnector($server);
    $result = $connector->reloadCore();
    if (!$result) {
      throw new SearchApiSolrException(sprintf('Reloading %s for %s (%s) failed.', $connector->isCloud() ? 'collection' : 'core', $server->label(), $server_id));
    }
    $this->reindex($server);
  }

  /**
   * Delete Solr collection.
   *
   * @param string $server_id
   *   The ID of the server.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function deleteCollection(string $server_id): void {
    $server = $this->getServer($server_id);
    $connector = Utility::getSolrCloudConnector($server);
    $result = $connector->deleteCollection();
    if (!$result) {
      throw new SearchApiSolrException(sprintf('Reloading %s for %s (%s) failed.', $connector->isCloud() ? 'collection' : 'core', $server->label(), $server_id));
    }
    $this->reindex($server);
  }

  /**
   * Generates and uploads the configset for a Solr search server.
   *
   * @param string $server_id
   *   The ID of the server.
   * @param array $collection_params
   *   The collection of parameters.
   * @param bool $messages
   *   Indicate if messages should be displayed, default is FALSE.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   * @throws \ZipStream\Exception\FileNotFoundException
   * @throws \ZipStream\Exception\FileNotReadableException
   * @throws \ZipStream\Exception\OverflowException
   */
  public function uploadConfigset(string $server_id, array $collection_params = [], bool $messages = FALSE): void {
    $server = $this->getServer($server_id);
    $connector = Utility::getSolrCloudConnector($server);

    if ($messages) {
      // Called via admin form. 'dt' is not available.
      $this->translationFunction = 't';
    }

    $filename = $this->fileSystem->tempnam($this->fileSystem->getTempDirectory(), 'configset_') . '.zip';
    $this->getServerConfigCommand($server->id(), $filename);

    $configset = $connector->getConfigSetName();
    $collection_exists = (bool) $configset;
    if (!$collection_exists) {
      $configset = Utility::generateConfigsetName($server);
    }

    $connector->uploadConfigset($configset, $filename);
    if ($messages) {
      $this->messenger->addStatus($this->t('Successfully uploaded configset %configset.', ['%configset' => $configset]));
    }

    if ($collection_exists) {
      $this->reload($server_id);
      if ($messages) {
        $this->messenger->addStatus($this->t('Successfully reloaded collection %collection.', ['%collection' => $connector->getCollectionName()]));
      }
    }
    else {
      $options = [];
      $allowed_options = [
        'numShards' => 'int',
        'maxShardsPerNode' => 'int',
        'replicationFactor' => 'int',
        'nrtReplicas' => 'int',
        'tlogReplicas' => 'int',
        'pullReplicas' => 'int',
        'autoAddReplicas' => 'bool',
        'alias' => 'string',
        'waitForFinalState' => 'bool',
        'createNodeSet' => 'string',
      ];

      foreach ($allowed_options as $option => $type) {
        if (isset($collection_params[$option]) && $collection_params[$option]) {
          switch ($type) {
            case 'int':
              $options[$option] = (int) $collection_params[$option];
              break;

            case'bool':
              $options[$option] = (bool) $collection_params[$option];
              break;

            case'string':
            default:
              $options[$option] = $collection_params[$option];
              break;
          }
        }
      }

      // Merge the param options.
      $collection = array_merge(
        [
          'collection.configName' => $configset,
        ],
        $options
      );

      $connector->createCollection($collection);

      if ($messages) {
        $this->messenger->addStatus($this->t('Successfully created collection %collection.', ['%collection' => $connector->getCollectionName()]));
      }
      $this->reindex($server);
    }
  }

}
