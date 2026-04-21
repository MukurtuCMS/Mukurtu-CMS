<?php

namespace Drupal\search_api_solr;

use Solarium\Core\Client\Endpoint;
use Solarium\Core\Client\State\ClusterState;
use Solarium\QueryType\Graph\Query as GraphQuery;
use Solarium\QueryType\Stream\Query as StreamQuery;

/**
 * The Solr Cloud connector interface.
 */
interface SolrCloudConnectorInterface extends SolrConnectorInterface {

  /**
   * Returns the Solr collection name.
   *
   * @return string
   *   The Solr collection name.
   */
  public function getCollectionName();

  /**
   * Temporarily set a different collection name for the connection.
   *
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   Solr endpoint.
   */
  public function setCollectionNameFromEndpoint(Endpoint $endpoint);

  /**
   * Returns the Solr collection name used to store topic checkpoints.
   *
   * @return string
   *   Solr collection name.
   */
  public function getCheckpointsCollectionName();

  /**
   * Returns the Solr collection endpoint used to store topic checkpoints.
   *
   * @return \Solarium\Core\Client\Endpoint|null
   *   Solr endpoint.
   */
  public function getCheckpointsCollectionEndpoint(): ?Endpoint;

  /**
   * Deletes all checkpoints for given index/site.
   *
   * @param string $index_id
   *   Id of the index.
   * @param string $site_hash
   *   The site hash.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function deleteCheckpoints(string $index_id, string $site_hash);

  /**
   * Returns a link to the Solr collection, if the necessary options are set.
   *
   * @return \Drupal\Core\Link
   *   The link to the Solr collection.
   */
  public function getCollectionLink();

  /**
   * Gets information about the Solr Collection.
   *
   * @param bool $reset
   *   If TRUE the server will be asked regardless if a previous call is cached.
   *
   * @return object
   *   A response object with system information.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getCollectionInfo($reset = FALSE);

  /**
   * Gets information about the cluster status of a Solr Collection.
   *
   * @param string|null $collection
   *   Collection.
   *
   * @return \Solarium\Core\Client\State\ClusterState|null
   *   A response object with system information.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getClusterStatus(?string $collection = NULL): ?ClusterState;

  /**
   * Reloads collection.
   *
   * @param string|null $collection
   *   Collection.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function reloadCollection(?string $collection = NULL): bool;

  /**
   * Creates collection.
   *
   * @param array $options
   *   The options array.
   * @param string|null $collection
   *   Collection.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function createCollection(array $options, ?string $collection = NULL): bool;

  /**
   * Deletes collection.
   *
   * @param string|null $collection
   *   Collection.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function deleteCollection(?string $collection = NULL): bool;

  /**
   * Upload a config-set to Solr Cloud.
   *
   * @param string $name
   *   The name of the configset within Solr.
   * @param string $filename
   *   The file name of the config-set to upload.
   *
   * @return bool
   *   Indicates if the upload succeeded.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function uploadConfigset(string $name, string $filename): bool;

  /**
   * Pings the Solr collection to tell whether it can be accessed.
   *
   * @return mixed
   *   The latency in milliseconds if the core can be accessed,
   *   otherwise FALSE.
   */
  public function pingCollection();

  /**
   * Creates a new Solarium stream query.
   *
   * @return \Solarium\QueryType\Stream\Query
   *   The Stream query.
   */
  public function getStreamQuery();

  /**
   * Creates a new Solarium graph query.
   *
   * @return \Solarium\QueryType\Graph\Query
   *   The Graph query.
   */
  public function getGraphQuery();

  /**
   * Executes a stream query.
   *
   * @param \Solarium\QueryType\Stream\Query $query
   *   The Solarium stream query.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint.
   *
   * @return \Solarium\QueryType\Stream\Result
   *   The Solarium stream result.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function stream(StreamQuery $query, ?Endpoint $endpoint = NULL);

  /**
   * Executes a graph query.
   *
   * @param \Solarium\QueryType\Graph\Query $query
   *   The Solarium graph query.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint.
   *
   * @return \Solarium\QueryType\Graph\Result
   *   The Solarium graph result.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function graph(GraphQuery $query, ?Endpoint $endpoint = NULL);

}
