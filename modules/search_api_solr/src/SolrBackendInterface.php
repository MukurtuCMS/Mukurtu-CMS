<?php

namespace Drupal\search_api_solr;

use Drupal\search_api\Contrib\AutocompleteBackendInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Query\QueryInterface;
use Solarium\Component\ComponentAwareQueryInterface;
use Solarium\Core\Client\Endpoint;
use Solarium\QueryType\Extract\Query;
use Solarium\QueryType\Update\Query\Query as UpdateQuery;

/**
 * Defines an interface for Solr search backend plugins.
 *
 * It extends the generic \Drupal\search_api\Backend\BackendInterface and covers
 * additional Solr specific methods.
 */
interface SolrBackendInterface extends AutocompleteBackendInterface {

  /**
   * The minimum required Solr schema version.
   */
  public const SEARCH_API_SOLR_MIN_SCHEMA_VERSION = '4.3.0';

  /**
   * The separator to indicate the start of a language ID.
   *
   * We must not use any character that has a special meaning within regular
   * expressions. Additionally, we have to avoid characters that are valid for
   * Drupal machine names.
   * The end of a language ID is indicated by an underscore '_' which could not
   * occur within the language ID itself because Drupal uses language tags.
   *
   * @see http://de2.php.net/manual/en/regexp.reference.meta.php
   * @see https://www.w3.org/International/articles/language-tags/
   */
  public const SEARCH_API_SOLR_LANGUAGE_SEPARATOR = ';';

  public const FIELD_PLACEHOLDER = 'FIELD_PLACEHOLDER';

  public const EMPTY_TEXT_FIELD_DUMMY_VALUE = 'aöbäcüdöeäfüg';

  /**
   * Get preferred schema version.
   *
   * @return string
   */
  public function getPreferredSchemaVersion(): string;

  /**
   * Get minmal required schema version.
   *
   * @return string
   */
  public function getMinimalRequiredSchemaVersion(): string;

  /**
   * Creates a list of all indexed field names mapped to their Solr field names.
   *
   * The special fields "search_api_id" and "search_api_relevance" are also
   * included. Any Solr fields that exist on search results are mapped back to
   * their local field names in the final result set.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search Api index.
   * @param bool $reset
   *   (optional) Whether to reset the static cache.
   *
   * @throws \Drupal\search_api\SearchApiException
   *
   * @see SearchApiSolrBackend::search()
   */
  public function getSolrFieldNames(IndexInterface $index, $reset = FALSE);

  /**
   * Gets a language-specific mapping from Drupal to Solr field names.
   *
   * @param string $language_id
   *   The language to get the mapping for.
   * @param \Drupal\search_api\IndexInterface|null $index
   *   (optional) The Search API index.
   * @param bool $reset
   *   (optional) Whether to reset the static cache.
   *
   * @return array
   *   The language-specific mapping from Drupal to Solr field names.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getLanguageSpecificSolrFieldNames($language_id, ?IndexInterface $index, $reset = FALSE);

  /**
   * Gets a language-specific mapping from Drupal to Solr field names.
   *
   * @param array $language_ids
   *   The language to get the mapping for.
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index entity.
   * @param bool $reset
   *   (optional) Whether to reset the static cache.
   *
   * @return array
   *   The language-specific mapping from Drupal to Solr field names.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getSolrFieldNamesKeyedByLanguage(array $language_ids, IndexInterface $index, $reset = FALSE);

  /**
   * Returns the Solr connector used for this backend.
   *
   * @return \Drupal\search_api_solr\SolrConnectorInterface
   *   The Solr connector object.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getSolrConnector();

  /**
   * Retrieves a Solr document from an search api index item.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index entity.
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   An item to get documents for.
   *
   * @return \Solarium\QueryType\Update\Query\Document
   *   A solr document.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getDocument(IndexInterface $index, ItemInterface $item);

  /**
   * Retrieves Solr documents from search api index items.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index.
   * @param \Drupal\search_api\Item\ItemInterface[] $items
   *   An array of items to get documents for.
   * @param \Solarium\QueryType\Update\Query\Query|null $update_query
   *   The existing update query the documents should be added to.
   *
   * @return \Solarium\QueryType\Update\Query\Document[]
   *   An array of solr documents.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getDocuments(IndexInterface $index, array $items, ?UpdateQuery $update_query = NULL);

  /**
   * Extract a file's content using tika within a solr server.
   *
   * @param string $filepath
   *   The real path of the file to be extracted.
   * @param string $extract_format
   *   The format to extract the content in.
   *
   * @return string
   *   The text extracted from the file.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function extractContentFromFile(string $filepath, string $extract_format = Query::EXTRACT_FORMAT_XML);

  /**
   * Returns the targeted content domain of the server.
   *
   * @return string
   *   The content domain.
   */
  public function getDomain();

  /**
   * Returns the targeted environment of the server.
   *
   * @return string
   *   The environment.
   */
  public function getEnvironment();

  /**
   * Indicates if the Solr server uses a managed schema.
   *
   * @return bool
   *   TRUE if the Solr server uses a managed schema, FALSE if the Solr server
   *   uses a classic schema.
   */
  public function isManagedSchema();

  /**
   * Indicates if the Solr index should be optimized daily.
   *
   * @return bool
   *   TRUE if the Solr index should be optimized daily, FALSE otherwise.
   */
  public function isOptimizeEnabled();

  /**
   * Returns a ready to use query string to filter results by index and site.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index entity.
   *
   * @return string
   *   The query string filter.
   */
  public function getIndexFilterQueryString(IndexInterface $index);

  /**
   * Returns the endpoint to use for the index.
   *
   * In case of Solr Cloud an index might use a different Solr collection.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index.
   *
   * @return \Solarium\Core\Client\Endpoint
   *   The solarium endpoint.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getCollectionEndpoint(IndexInterface $index);

  /**
   * Prefixes an index ID as configured.
   *
   * The resulting ID will be a concatenation of the following strings:
   * - If set, the server-specific index_prefix.
   * - If set, the index-specific prefix.
   * - The index's machine name.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index entity.
   *
   * @return string
   *   The prefixed machine name.
   */
  public function getIndexId(IndexInterface $index);

  /**
   * Returns the targeted Index ID. In case of multisite it might differ.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index entity.
   *
   * @return string
   *   The targeted Index ID.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getTargetedIndexId(IndexInterface $index);

  /**
   * Returns the targeted site hash. In case of multisite it might differ.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index entity.
   *
   * @return string
   *   The targeted site hash.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getTargetedSiteHash(IndexInterface $index);

  /**
   * Executes a streaming expression.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query used for the streaming expression.
   *
   * @return \Solarium\QueryType\Stream\Result
   *   The streaming expression result.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function executeStreamingExpression(QueryInterface $query);

  /**
   * Executes a graph streaming expression.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query used for the graph streaming expression.
   *
   * @return \Solarium\QueryType\Graph\Result
   *   The graph streaming expression result.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function executeGraphStreamingExpression(QueryInterface $query);

  /**
   * Apply any finalization commands to a solr index.
   *
   * Only if globally configured to do so and only the first time after changes
   * to the index from the drupal side.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index entity.
   *
   * @return bool
   *   TRUE if a finalization run, FALSE otherwise. FALSE doesn't indicate an
   *   error!
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function finalizeIndex(IndexInterface $index);

  /**
   * Gets schema language statistics for the multilingual Solr server.
   *
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   If not set, the statistics for the server's default endpoint will be
   *   returned.
   *
   * @return array
   *   Stats as associative array keyed by language IDs. The value is the
   *   language id of the corresponding field type existing on the server's
   *   current schema or FALSE.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getSchemaLanguageStatistics(?Endpoint $endpoint = NULL);

  /**
   * Get document counts for this server, in total and per site / index.
   *
   * @return array
   *   An associative array of document counts.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getDocumentCounts();

  /**
   * Get the max document versions, in total and per site / index / datasource.
   *
   * _version_ numbers are important for replication and checkpoints.
   *
   * @return array
   *   An associative array of max document versions.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getMaxDocumentVersions();

  /**
   * Gets a list of Solr Field Types that are disabled for this backend.
   *
   * @return String[]
   *   The list of Solr Field Types that are disabled for this backend.
   */
  public function getDisabledFieldTypes(): array;

  /**
   * Gets a list of Solr Caches that are disabled for this backend.
   *
   * @return String[]
   *   The list of Solr Caches that are disabled for this backend.
   */
  public function getDisabledCaches(): array;

  /**
   * Gets a list of Solr Request Handlers that are disabled for this backend.
   *
   * @return String[]
   *   The list of Solr Request Handlers.
   */
  public function getDisabledRequestHandlers(): array;

  /**
   * Gets a list of Solr Request Dispatchers that are disabled for this backend.
   *
   * @return String[]
   *   The list of Solr Request Dispatchers.
   */
  public function getDisabledRequestDispatchers(): array;

  /**
   * Indicates if the current Solr config should not be verified.
   *
   * @return bool
   *   Whether a non-drupal or an outdated config-set is allowed or not.
   */
  public function isNonDrupalOrOutdatedConfigSetAllowed(): bool;

  /**
   * Provide an easy to access event dispatcher for plugins.
   *
   * @param object $event
   *   The object to process.
   *
   * @return object
   *   The Event that was passed, now modified by listeners.
   *
   * @see \Psr\EventDispatcher\EventDispatcherInterface
   */
  public function dispatch(object $event): void;

  /**
   * Adds spellcheck features to the search query.
   *
   * @param \Solarium\Component\ComponentAwareQueryInterface $solarium_query
   *   The Solarium query.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query.
   * @param array $spellcheck_options
   *   The spellcheck options to add.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function setSpellcheck(ComponentAwareQueryInterface $solarium_query, QueryInterface $query, array $spellcheck_options = []);

}
