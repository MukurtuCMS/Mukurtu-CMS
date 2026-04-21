<?php

namespace Drupal\search_api_solr_admin\Commands;

use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr_admin\Utility\SolrAdminCommandHelper;
use Drush\Commands\DrushCommands;
use Psr\Log\LoggerInterface;

/**
 * Defines Drush commands for the Search API Solr Admin.
 */
class SearchApiSolrAdminCommands extends DrushCommands implements StdinAwareInterface {

  use StdinAwareTrait;

  /**
   * The command helper.
   *
   * @var \Drupal\search_api_solr_admin\Utility\SolrAdminCommandHelper
   */
  protected $commandHelper;

  /**
   * Constructs a SearchApiSolrCommands object.
   *
   * @param \Drupal\search_api_solr_admin\Utility\SolrAdminCommandHelper $commandHelper
   *   The command helper.
   */
  public function __construct(SolrAdminCommandHelper $commandHelper) {
    parent::__construct();
    $this->commandHelper = $commandHelper;
  }

  /**
   * {@inheritdoc}
   */
  public function setLogger(LoggerInterface $logger): void {
    parent::setLogger($logger);
    $this->commandHelper->setLogger($logger);
  }

  /**
   * Reload Solr core or collection.
   *
   * @param string $server_id
   *   The ID of the server.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   *
   * @command search-api-solr:reload
   *
   * @usage drush search-api-solr:reload server_id
   *   Forces the Solr server to reload the core or collection to apply config
   *   changes.
   *
   * @aliases solr-reload
   */
  public function reload(string $server_id): void {
    $this->commandHelper->reload($server_id);
    $this->logger()->success(dt('Solr core/collection of %server_id reloaded.', ['%server_id' => $server_id]));
  }

  /**
   * Delete Solr collection.
   *
   * @param string $server_id
   *   The ID of the server.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   *
   * @command search-api-solr:delete-collection
   *
   * @usage drush search-api-solr:delete-collection server_id
   *   Forces the Solr server to delete the collection.
   *
   * @aliases solr-delete-collection
   */
  public function deleteCollection(string $server_id): void {
    $this->commandHelper->deleteCollection($server_id);
    $this->logger()->success(dt('Solr collection of %server_id deleted.', ['%server_id' => $server_id]));
  }

  /**
   * Deletes *all* documents on a Solr search server (including all indexes).
   *
   * @param string $server_id
   *   The ID of the server.
   *
   * @command search-api-solr:delete-all
   *
   * @usage search-api-solr:delete-all server_id
   *   Deletes *all* documents on server_id.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function deleteAll(string $server_id): void {
    $servers = $this->commandHelper->loadServers([$server_id]);
    if ($server = reset($servers)) {
      $backend = $server->getBackend();
      if ($backend instanceof SolrBackendInterface) {
        $connector = $backend->getSolrConnector();
        $update_query = $connector->getUpdateQuery();
        $update_query->addDeleteQuery('*:*');
        $connector->update($update_query);

        foreach ($server->getIndexes() as $index) {
          if ($index->status() && !$index->isReadOnly()) {
            if ($connector->isCloud()) {
              $connector->update($update_query, $backend->getCollectionEndpoint($index));
            }
            $index->reindex();
          }
        }
      }
      else {
        throw new SearchApiSolrException("The given server ID doesn't use the Solr backend.");
      }
    }
    else {
      throw new SearchApiException("The given server ID doesn't exist.");
    }
  }

  /**
   * Upload a configset and reload the collection or create it using the given options.
   *
   * @param string $server_id
   *   The ID of the server.
   * @param array $options
   *   Additional options for the command.
   *
   * @option numShards
   *   The number of shards to be created as part of the collection. This option
   *   is ignored if the collection already exists.
   * @option maxShardsPerNode
   *   When creating collections, the shards and/or replicas are spread across
   *   all available (i.e., live) nodes, and two replicas of the same shard will
   *   never be on the same node. If a node is not live when the CREATE action
   *   is called, it will not get any parts of the new collection, which could
   *   lead to too many replicas being created on a single live node. Defining
   *   maxShardsPerNode sets a limit on the number of replicas the CREATE action
   *   will spread to each node. If the entire collection can not be fit into
   *   the live nodes, no collection will be created at all. The default
   *   maxShardsPerNode value is 1. A value of -1 means unlimited. If a policy
   *   is also specified then the stricter of maxShardsPerNode and policy rules
   *   apply. This option is ignored if the collection already exists.
   * @option replicationFactor
   *   The number of replicas to be created for each shard. The default is 1.
   *   This will create a NRT type of replica. If you want another type of
   *   replica, see the tlogReplicas and pullReplica parameters below. This
   *   option is ignored if the collection already exists.
   * @option nrtReplicas
   *   The number of NRT (Near-Real-Time) replicas to create for this
   *   collection. This type of replica maintains a transaction log and updates
   *   its index locally. If you want all of your replicas to be of this type,
   *   you can simply use replicationFactor instead. This option is ignored if
   *   the collection already exists.
   * @option tlogReplicas
   *   The number of TLOG replicas to create for this collection. This type of
   *   replica maintains a transaction log but only updates its index via
   *   replication from a leader. This option is ignored if the collection
   *   already exists.
   * @option pullReplicas
   *   The number of PULL replicas to create for this collection. This type of
   *   replica does not maintain a transaction log and only updates its index
   *   via replication from a leader. This type is not eligible to become a
   *   leader and should not be the only type of replicas in the collection.
   * @option autoAddReplicas
   *   When set to true, enables automatic addition of replicas when the number
   *   of active replicas falls below the value set for replicationFactor. This
   *   may occur if a replica goes down, for example. The default is false,
   *   which means new replicas will not be added. This option is ignored if the
   *   collection already exists.
   * @option alias
   *   Starting with Solr version 8.1 when a collection is created additionally
   *   an alias can be created that points to this collection. This parameter
   *   allows specifying the name of this alias, effectively combining this
   *   operation with CREATEALIAS. This option is ignored if the collection
   *   already exists.
   * @option waitForFinalState
   *   If true, the request will complete only when all affected replicas become
   *   active. The default is false, which means that the API will return the
   *   status of the single action, which may be before the new replica is
   *   online and active.
   * @option createNodeSet
   *   Allows defining the nodes to spread the new collection across. The format
   *   is a comma-separated list of node_names, such as
   *   localhost:8983_solr,localhost:8984_solr,localhost:8985_solr. If not
   *   provided, the CREATE operation will create shard-replicas spread across
   *   all live Solr nodes. Alternatively, use the special value of EMPTY to
   *   initially create no shard-replica within the new collection and then
   *   later use the ADDREPLICA operation to add shard-replicas when and where
   *   required. This option is ignored if the collection already exists.
   *
   * @default $options []
   *
   * @see https://solr.apache.org/guide/8_11/collection-management.html
   *
   * @command search-api-solr:upload-configset Json array of arguments to pass to the Collections API
   *
   * @usage drush search-api-solr:upload-configset --numShards=3 --replicationFactor=2 SERVER_ID
   *   Upload a configset and reload the collection or create it with 3 shards
   *   and a replication factor of 2 for Search API Server SERVER_ID.
   *
   * @aliases solr-upload-conf
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   * @throws \ZipStream\Exception\FileNotFoundException
   * @throws \ZipStream\Exception\FileNotReadableException
   * @throws \ZipStream\Exception\OverflowException
   */
  public function uploadConfigset(string $server_id, array $options = [
    'numShards' => 3,
    'maxShardsPerNode' => 1,
    'replicationFactor' => 1,
    'nrtReplicas' => 0,
    'tlogReplicas' => 0,
    'pullReplicas' => 0,
    'autoAddReplicas' => FALSE,
    'alias' => '',
    'waitForFinalState' => FALSE,
    'createNodeSet' => '',
  ]): void {
    $this->commandHelper->uploadConfigset($server_id, $options, $this->output()->isVerbose());
    $this->logger()->success(dt('Solr configset for %server_id uploaded.', ['%server_id' => $server_id]));
  }

}
