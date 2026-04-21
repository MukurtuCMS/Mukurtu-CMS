<?php

namespace Drupal\search_api_solr;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\search_api_solr\Solarium\Autocomplete\Query as AutocompleteQuery;
use Solarium\Core\Client\Endpoint;
use Solarium\Core\Client\Request;
use Solarium\Core\Client\Response;
use Solarium\Core\Query\QueryInterface;
use Solarium\QueryType\Analysis\Query\AbstractQuery;
use Solarium\QueryType\Analysis\Query\Field;
use Solarium\QueryType\Extract\Result as ExtractResult;
use Solarium\QueryType\Select\Query\Query;
use Solarium\QueryType\Update\Query\Query as UpdateQuery;
use ZipStream\ZipStream;

/**
 * The Solr connector interface.
 */
interface SolrConnectorInterface extends ConfigurableInterface {

  const QUERY_TIMEOUT = 'query_timeout';
  const INDEX_TIMEOUT = 'index_timeout';
  const OPTIMIZE_TIMEOUT = 'optimize_timeout';
  const FINALIZE_TIMEOUT = 'finalize_timeout';

  /**
   * Sets the event dispatcher.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The container aware event dispatcher.
   */
  public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): SolrConnectorInterface;

  /**
   * Returns TRUE for Cloud.
   *
   * @return bool
   *   Whether this is a Solr Cloud connector.
   */
  public function isCloud();

  /**
   * Returns TRUE if the connector supports a Solr trusted context.
   *
   * @return bool
   *   Whether the connector supports a Solr trusted context.
   */
  public function isTrustedContextSupported();

  /**
   * Returns a link to the Solr server.
   *
   * @return \Drupal\Core\Link
   *   The link object to the Solr server.
   */
  public function getServerLink();

  /**
   * Returns a link to the Solr core, if the necessary options are set.
   *
   * @return \Drupal\Core\Link
   *   The link object to the Solr core.
   */
  public function getCoreLink();

  /**
   * Gets the current Solr version.
   *
   * @param bool $force_auto_detect
   *   If TRUE, ignore user overwrites.
   *
   * @return string
   *   The full Solr version string.
   */
  public function getSolrVersion($force_auto_detect = FALSE);

  /**
   * Gets the current Solr major version.
   *
   * @param string $version
   *   An optional Solr version string.
   *
   * @return int
   *   The Solr major version.
   */
  public function getSolrMajorVersion($version = ''): int;

  /**
   * Gets the current Solr branch name.
   *
   * @param string $version
   *   An optional Solr version string.
   *
   * @return string
   *   The Solr branch string.
   */
  public function getSolrBranch($version = '');

  /**
   * Gets the LuceneMatchVersion string.
   *
   * @param string $version
   *   An optional Solr version string.
   *
   * @return string
   *   The lucene match version in Major.Minor(.Patch) format.
   */
  public function getLuceneMatchVersion($version = '');

  /**
   * Gets the current Lucene version deployed on Solr server.
   *
   * @return string
   *   The full Lucene version string.
   */
  public function getLuceneVersion();

  /**
   * Gets information about the Solr server.
   *
   * @param bool $reset
   *   If TRUE the server will be asked regardless if a previous call is cached.
   *
   * @return object
   *   A response object with server information.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getServerInfo($reset = FALSE);

  /**
   * Gets information about the Solr Core.
   *
   * @param bool $reset
   *   If TRUE the server will be asked regardless if a previous call is cached.
   *
   * @return object
   *   A response object with system information.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getCoreInfo($reset = FALSE);

  /**
   * Gets meta-data about the index.
   *
   * @return object
   *   A response object filled with data from Solr's Luke.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getLuke();

  /**
   * Gets the name of the used configset.
   *
   * @return string|null
   *   Configset name.
   */
  public function getConfigSetName(): ?string;

  /**
   * Gets the full schema version string the core is using.
   *
   * @param bool $reset
   *   If TRUE the server will be asked regardless if a previous call is cached.
   *
   * @return string
   *   The full schema version string.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getSchemaVersionString($reset = FALSE);

  /**
   * Gets the schema version number.
   *
   * @param bool $reset
   *   If TRUE the server will be asked regardless if a previous call is cached.
   *
   * @return string
   *   The schema version number.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getSchemaVersion($reset = FALSE);

  /**
   * Gets the Solr branch targeted by the schema.
   *
   * @param bool $reset
   *   If TRUE the server will be asked regardless if a previous call is cached.
   *
   * @return string
   *   The targeted Solr branch.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getSchemaTargetedSolrBranch($reset = FALSE);

  /**
   * Indicates if the Solr config-set is our jum-start config-set.
   *
   * @param bool $reset
   *   If TRUE the server will be asked regardless if a previous call is cached.
   *
   * @return bool
   *   The targeted Solr branch.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function isJumpStartConfigSet(bool $reset = FALSE): bool;

  /**
   * Pings the Solr core to tell whether it can be accessed.
   *
   * @param array $options
   *   (optional) An array of options.
   *
   * @return mixed
   *   The latency in milliseconds if the core can be accessed,
   *   otherwise FALSE.
   */
  public function pingCore(array $options = []);

  /**
   * Pings the Solr server to tell whether it can be accessed.
   *
   * @return mixed
   *   The latency in milliseconds if the core can be accessed,
   *   otherwise FALSE.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function pingServer();

  /**
   * Pings the Solr endpoint to tell whether it can be accessed.
   *
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   The endpoint.
   * @param array $options
   *   (optional) An array of options.
   *
   * @return mixed
   *   The latency in milliseconds if the endpoint can be accessed,
   *   otherwise FALSE.
   */
  public function pingEndpoint(?Endpoint $endpoint = NULL, array $options = []);

  /**
   * Gets summary information about the Solr Core.
   *
   * @return array
   *   An array of stats about the solr core.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getStatsSummary();

  /**
   * Sends a REST GET request to the Solr core and returns the result.
   *
   * @param string $path
   *   The path to append to the base URI.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   The endpoint.
   *
   * @return string
   *   The decoded response.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function coreRestGet($path, ?Endpoint $endpoint = NULL);

  /**
   * Sends a REST POST request to the Solr core and returns the result.
   *
   * @param string $path
   *   The path to append to the base URI.
   * @param string $command_json
   *   The command to send encoded as JSON.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   The endpoint.
   *
   * @return string
   *   The decoded response.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function coreRestPost($path, $command_json = '', ?Endpoint $endpoint = NULL);

  /**
   * Sends a REST GET request to the Solr server and returns the result.
   *
   * @param string $path
   *   The path to append to the base URI.
   *
   * @return string
   *   The decoded response.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function serverRestGet($path);

  /**
   * Sends a REST POST request to the Solr server and returns the result.
   *
   * @param string $path
   *   The path to append to the base URI.
   * @param string $command_json
   *   The command to send encoded as JSON.
   *
   * @return string
   *   The decoded response.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function serverRestPost($path, $command_json = '');

  /**
   * Creates a new Solarium update query.
   *
   * @return \Solarium\QueryType\Update\Query\Query
   *   The Update query.
   */
  public function getUpdateQuery();

  /**
   * Creates a new Solarium update query.
   *
   * @return \Solarium\QueryType\Select\Query\Query
   *   The Select query.
   */
  public function getSelectQuery();

  /**
   * Creates a new Solarium more like this query.
   *
   * @return \Solarium\QueryType\MoreLikeThis\Query
   *   The MoreLikeThis query.
   */
  public function getMoreLikeThisQuery();

  /**
   * Creates a new Solarium terms query.
   *
   * @return \Solarium\QueryType\Terms\Query
   *   The Terms query.
   */
  public function getTermsQuery();

  /**
   * Creates a new Solarium suggester query.
   *
   * @return \Solarium\QueryType\Spellcheck\Query
   *   The Spellcheck query.
   */
  public function getSpellcheckQuery();

  /**
   * Creates a new Solarium suggester query.
   *
   * @return \Solarium\QueryType\Suggester\Query
   *   The Suggester query.
   */
  public function getSuggesterQuery();

  /**
   * Creates a new Solarium autocomplete query.
   *
   * @return \Drupal\search_api_solr\Solarium\Autocomplete\Query
   *   The Autocomplete query.
   */
  public function getAutocompleteQuery();

  /**
   * Creates a new Solarium extract query.
   *
   * @return \Solarium\QueryType\Extract\Query
   *   The Extract query.
   */
  public function getExtractQuery();

  /**
   * Creates a new Solarium analysis query.
   */
  public function getAnalysisQueryField(): Field;

  /**
   * Returns a Solarium query helper object.
   *
   * @param \Solarium\Core\Query\QueryInterface|null $query
   *   (optional) A Solarium query object.
   *
   * @return \Solarium\Core\Query\Helper
   *   A Solarium query helper.
   */
  public function getQueryHelper(?QueryInterface $query = NULL);

  /**
   * Executes a search query and returns the raw response.
   *
   * @param \Solarium\QueryType\Select\Query\Query $query
   *   The Solarium select query object.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint object.
   *
   * @return \Solarium\Core\Client\Response
   *   The Solarium response object.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function search(Query $query, ?Endpoint $endpoint = NULL);

  /**
   * Creates a result from a response.
   *
   * @param \Solarium\Core\ConfigurableInterface|QueryInterface $query
   *   The Solarium query object.
   * @param \Solarium\Core\Client\Response $response
   *   The Solarium response object.
   *
   * @return \Solarium\Core\Query\Result\ResultInterface
   *   The Solarium result object.
   */
  public function createSearchResult(QueryInterface $query, Response $response);

  /**
   * Executes an update query and applies some tweaks.
   *
   * @param \Solarium\QueryType\Update\Query\Query $query
   *   The Solarium update query object.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint object.
   *
   * @return \Solarium\Core\Query\Result\ResultInterface
   *   The Solarium result object.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function update(UpdateQuery $query, ?Endpoint $endpoint = NULL);

  /**
   * Executes a search query and returns the raw response.
   *
   * @param \Drupal\search_api_solr\Solarium\Autocomplete\Query $query
   *   The Solarium select query object.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint object.
   *
   * @return \Solarium\Core\Query\Result\ResultInterface
   *   The Solarium Result object.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function autocomplete(AutocompleteQuery $query, ?Endpoint $endpoint = NULL);

  /**
   * Executes an analysis query and returns the raw response.
   *
   * @param \Solarium\QueryType\Analysis\Query\AbstractQuery $query
   *   The Solarium select query object.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint object.
   *
   * @return \Solarium\Core\Query\Result\ResultInterface
   *   The Solarium Result object.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function analyze(AbstractQuery $query, ?Endpoint $endpoint = NULL);

  /**
   * Executes any query.
   *
   * @param \Solarium\Core\Query\QueryInterface $query
   *   The Solarium query object.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint object.
   *
   * @return \Solarium\Core\Query\Result\ResultInterface
   *   The Solarium result object.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function execute(QueryInterface $query, ?Endpoint $endpoint = NULL);

  /**
   * Executes a request and returns the response.
   *
   * @param \Solarium\Core\Client\Request $request
   *   The Solarium request object.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint object.
   *
   * @return \Solarium\Core\Client\Response
   *   The Solarium response object.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function executeRequest(Request $request, ?Endpoint $endpoint = NULL);

  /**
   * Executes any query but don't wait for Solr's response.
   *
   * @param \Solarium\Core\Query\QueryInterface $query
   *   The Solarium query object.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint object.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function fireAndForget(QueryInterface $query, ?Endpoint $endpoint = NULL): void;

  /**
   * Optimizes the Solr index.
   *
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint object.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function optimize(?Endpoint $endpoint = NULL);

  /**
   * Executes an extract query.
   *
   * @param \Solarium\Core\Query\QueryInterface|\Solarium\QueryType\Extract\Query $query
   *   The Solarium extract query object.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint object.
   *
   * @return \Solarium\QueryType\Extract\Result
   *   The Solarium extract result object.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function extract(QueryInterface $query, ?Endpoint $endpoint = NULL);

  /**
   * Gets the content from an extract query result.
   *
   * @param \Solarium\QueryType\Extract\Result $result
   *   The Solarium extract result object.
   * @param string $filepath
   *   The filepath to look for in results.
   *
   * @return string
   *   The extracted content as string.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getContentFromExtractResult(ExtractResult $result, $filepath);

  /**
   * Returns an endpoint.
   *
   * @param string $key
   *   The endpoint ID.
   *
   * @return \Solarium\Core\Client\Endpoint
   *   The Solarium endpoint object.
   *
   * @throws \Solarium\Exception\OutOfBoundsException
   */
  public function getEndpoint($key = 'search_api_solr');

  /**
   * Creates an endpoint.
   *
   * @param string $key
   *   The endpoint ID.
   * @param array $additional_configuration
   *   Configuration in addtion to the default configuration.
   *
   * @return \Solarium\Core\Client\Endpoint
   *   The Solarium endpoint object.
   */
  public function createEndpoint(string $key, array $additional_configuration = []);

  /**
   * Retrieves a config file or file list from the Solr server.
   *
   * Uses the admin/file request handler.
   *
   * @param string|null $file
   *   (optional) The name of the file to retrieve. If the file is a directory,
   *   the directory contents are instead listed and returned. NULL represents
   *   the root config directory.
   *
   * @return \Solarium\Core\Client\Response|array
   *   A Solarium response object containing either the file contents or a file
   *   list.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getFile($file = NULL);

  /**
   * Returns additional, connector-specific information about this server.
   *
   * This information will be then added to the server's "View" tab in some way.
   * In the default theme implementation the data is output in a table with two
   * columns along with other, generic information about the server.
   *
   * @return array
   *   An array of additional server information, with each piece of information
   *   being an associative array with the following keys:
   *   - label: The human-readable label for this data.
   *   - info: The information, as HTML.
   *   - status: (optional) The status associated with this information. One of
   *     "info", "ok", "warning" or "error". Defaults to "info".
   */
  public function viewSettings();

  /**
   * Reloads the Solr core.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function reloadCore();

  /**
   * Sets a new timeout for queries, but not for indexing or optimization.
   *
   * The timeout will not be saved in the configuration of the connector. It
   * will be overwritten for the current request only.
   *
   * @param int $seconds
   *   The new query timeout value to set.
   * @param string $timeout
   *   (optional) The configured timeout to use. Default is self::QUERY_TIMEOUT.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint object.
   *
   * @return int
   *   The previous query timeout value.
   */
  public function adjustTimeout(int $seconds, string $timeout = self::QUERY_TIMEOUT, ?Endpoint &$endpoint = NULL): int;

  /**
   * Get the query timeout.
   *
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint object.
   *
   * @return int
   *   The current query timeout value.
   */
  public function getTimeout(?Endpoint $endpoint = NULL);

  /**
   * Get the index timeout.
   *
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint object.
   *
   * @return int
   *   The current index timeout value.
   */
  public function getIndexTimeout(?Endpoint $endpoint = NULL);

  /**
   * Get the optimize timeout.
   *
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint object.
   *
   * @return int
   *   The current optimize timeout value.
   */
  public function getOptimizeTimeout(?Endpoint $endpoint = NULL);

  /**
   * Get the finalize timeout.
   *
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint object.
   *
   * @return int
   *   The current finalize timeout value.
   */
  public function getFinalizeTimeout(?Endpoint $endpoint = NULL);

  /**
   * Alter the newly assembled Solr configuration files.
   *
   * @param string[] $files
   *   Array of config files keyed by file names.
   * @param string $lucene_match_version
   *   Lucene (Solr) minor version string.
   * @param string $server_id
   *   Optional Search API server id. Will be set in most cases but might be
   *   empty when the config generation is triggered via UI or drush.
   */
  public function alterConfigFiles(array &$files, string $lucene_match_version, string $server_id = '');

  /**
   * Alter the zip archive of newly assembled Solr configuration files.
   *
   * @param \ZipStream\ZipStream $zip
   *   Zip archive.
   * @param string $lucene_match_version
   *   Lucene (Solr) minor version string.
   * @param string $server_id
   *   Optional Search API server id. Will be set in most cases but might be
   *   empty when the config generation is triggered via UI or drush.
   */
  public function alterConfigZip(ZipStream $zip, string $lucene_match_version, string $server_id = '');

}
