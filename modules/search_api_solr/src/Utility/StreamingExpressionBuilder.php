<?php

namespace Drupal\search_api_solr\Utility;

use Drupal\Core\Language\LanguageInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\SolrCloudConnectorInterface;
use Drupal\search_api_solr\SolrProcessorInterface;
use Solarium\QueryType\Stream\ExpressionBuilder;

/**
 * Provides methods for creating streaming expressions targeting a given index.
 */
class StreamingExpressionBuilder extends ExpressionBuilder {

  /**
   * The Solr collection name.
   *
   * @var string
   */
  protected $collection;

  /**
   * The query string to filter results by index and site.
   *
   * @var string
   */
  protected $checkpointsCollection;

  /**
   * The index filter query.
   *
   * @var string
   */
  protected $indexFilterQuery;

  /**
   * The targeted Index ID.
   *
   * @var string
   */
  protected $targetedIndexId;

  /**
   * The targeted site hash.
   *
   * @var string
   */
  protected $targetedSiteHash;

  /**
   * The Search API index entity.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The server ID.
   *
   * @var string
   */
  protected $serverId;

  /**
   * The formatted request time.
   *
   * @var string
   */
  protected $requestTime;

  /**
   * The array of all mapped fields including graph fields.
   *
   * @var string[][]
   */
  protected $allFieldsIncludingGraphFieldsMapped;

  /**
   * The array of all mapped fields.
   *
   * @var string[][]
   */
  protected $allFieldsMapped;

  /**
   * The array of all mapped fields that have docValues.
   *
   * @var string[][]
   */
  protected $allDocValueFieldsMapped;

  /**
   * The array of all mapped sorting fields.
   *
   * @var string[][]
   */
  protected $sortFieldsMapped;

  /**
   * The Solarium query helper.
   *
   * @var \Solarium\Core\Query\Helper
   */
  protected $queryHelper;

  /**
   * The _search_all() and _topic_all() streaming expressions need a row limit.
   *
   * The row limit is equal to or higher then the real number of rows.
   *
   * @var int
   */
  protected $searchAllRows;

  /**
   * The Solr backend.
   *
   * @var \Drupal\search_api_solr\SolrBackendInterface
   */
  protected $backend;

  /**
   * The Solr connector.
   *
   * @var \Drupal\search_api_solr\SolrBackendInterface
   */
  protected $connector;

  /**
   * StreamingExpressionBuilder constructor.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index entity.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function __construct(IndexInterface $index) {
    $server = $index->getServerInstance();
    $this->serverId = $server->id();
    $this->backend = $server->getBackend();
    $this->connector = $this->backend->getSolrConnector();
    $index_settings = Utility::getIndexSolrSettings($index);

    if (!($this->connector instanceof SolrCloudConnectorInterface)) {
      throw new SearchApiSolrException('Streaming expressions are only supported by a Solr Cloud connector.');
    }

    $language_ids = array_merge(array_keys(\Drupal::languageManager()->getLanguages()), [LanguageInterface::LANGCODE_NOT_SPECIFIED]);
    $this->collection = $index_settings['advanced']['collection'] ?: $this->connector->getCollectionName();
    $this->checkpointsCollection = $this->connector->getCheckpointsCollectionName();
    $this->indexFilterQuery = $this->backend->getIndexFilterQueryString($index);
    $this->targetedIndexId = $this->backend->getTargetedIndexId($index);
    $this->targetedSiteHash = $this->backend->getTargetedSiteHash($index);
    $this->index = $index;
    $this->requestTime = $this->backend->formatDate(\Drupal::time()->getRequestTime());
    $this->allFieldsMapped = [];
    foreach ($this->backend->getSolrFieldNamesKeyedByLanguage($language_ids, $index) as $search_api_field => $solr_field) {
      foreach ($solr_field as $language_id => $solr_field_name) {
        $this->allFieldsMapped[$language_id][$search_api_field] = $solr_field_name;
      }
    }
    foreach ($language_ids as $language_id) {
      $this->allFieldsMapped[$language_id] += [
        // Search API Solr Search specific fields.
        'id' => Utility::hasIndexJustSolrDocumentDatasource($index) ? $index->get('datasource_settings')['solr_document']['id_field'] : 'id',
        'index_id' => 'index_id',
        'hash' => 'hash',
        'site' => 'site',
        'timestamp' => 'timestamp',
        'context_tags' => 'sm_context_tags',
        // @todo to be removed
        'spell' => 'spell',
      ];

      $this->allFieldsIncludingGraphFieldsMapped[$language_id] = $this->allFieldsMapped[$language_id] + [
        // Graph traversal reserved names. We can't get a conflict here since
        // all dynamic fields are prefixed.
        'node' => 'node',
        'collection' => 'collection',
        'field' => 'field',
        'level' => 'level',
        'ancestors' => 'ancestors',
      ];

      $this->sortFieldsMapped[$language_id] = [];
      if (!Utility::hasIndexJustSolrDocumentDatasource($index)) {
        foreach ($this->allFieldsMapped[$language_id] as $search_api_field => $solr_field) {
          if (strpos($solr_field, 't') === 0 || strpos($solr_field, 's') === 0) {
            $this->sortFieldsMapped[$language_id]['sort_' . $search_api_field] = Utility::encodeSolrName('sort' . SolrBackendInterface::SEARCH_API_SOLR_LANGUAGE_SEPARATOR . $language_id . '_' . $search_api_field);
          }
          elseif (preg_match('/^([a-z]+)m(_.*)/', $solr_field, $matches) && strpos($solr_field, 'random_') !== 0) {
            $this->sortFieldsMapped[$language_id]['sort' . Utility::decodeSolrName($matches[2])] = $matches[1] . 's' . $matches[2];
          }

          if (
            // Covers sort_*, too.
            strpos($solr_field, 's') === 0 ||
            strpos($solr_field, 'i') === 0 ||
            strpos($solr_field, 'f') === 0 ||
            strpos($solr_field, 'p') === 0 ||
            strpos($solr_field, 'b') === 0 ||
            strpos($solr_field, 'h') === 0
          ) {
            $this->allDocValueFieldsMapped[$language_id][$search_api_field] = $solr_field;
          }
        }
      }
    }

    $this->queryHelper = $this->connector->getQueryHelper();
  }

  /**
   * Returns the Solr Cloud collection name for the current index.
   *
   * @return string
   *   The collection name.
   */
  public function _collection() {
    return $this->collection;
  }

  /**
   * Returns the Solr Cloud collection name for storing topic checkpoints.
   *
   * @return string
   *   The checkpoints collection name.
   */
  public function _checkpoints_collection() {
    return $this->checkpointsCollection;
  }

  /**
   * Converts a Search API field name into a Solr field name.
   *
   * @param string $search_api_field_name
   *   The Search API field name.
   * @param string $language_id
   *   (optional) The language ID. Defaults to "und".
   *
   * @return string
   *   The Solr field name.
   *
   * @throws \InvalidArgumentException
   */
  public function _field(string $search_api_field_name, string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    if (!isset($this->allFieldsIncludingGraphFieldsMapped[$language_id][$search_api_field_name])) {
      if (isset($this->sortFieldsMapped[$language_id][$search_api_field_name])) {
        return $this->sortFieldsMapped[$language_id][$search_api_field_name];
      }

      throw new \InvalidArgumentException(sprintf('Field %s does not exist in index %s.', $search_api_field_name, $this->targetedIndexId));
    }
    return $this->allFieldsIncludingGraphFieldsMapped[$language_id][$search_api_field_name];
  }

  /**
   * Formats a list of Search API field names into a string of Solr field names.
   *
   * @param array $search_api_field_names
   *   The array of Search API field names.
   * @param string $delimiter
   *   (optional) The delimiter to use. Defaults to ",".
   * @param string $language_id
   *   (optional) The language ID. Defaults to "und".
   *
   * @return string
   *   A list of Solr field names.
   */
  public function _field_list(array $search_api_field_names, string $delimiter = ',', string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    return trim(array_reduce(
      $search_api_field_names,
      function ($carry, $search_api_field_name) use ($delimiter, $language_id) {
        return $carry . $this->_field($search_api_field_name, $language_id) . $delimiter;
      },
      ''
    ), $delimiter);
  }

  /**
   * Formats the list of all Search API fields as a string of Solr field names.
   *
   * @param string $delimiter
   *   (optional) The delimiter to use. Defaults to ",".
   * @param bool $include_sorts
   *   (optional) Whether to include sort fields. Defaults to TRUE.
   * @param array $blacklist
   *   (optional) An array of field names to exclude.
   * @param string $language_id
   *   (optional) The language ID. Defaults to "und".
   *
   * @return string
   *   A list of all Solr field names for the index.
   */
  public function _all_fields_list(string $delimiter = ',', bool $include_sorts = TRUE, array $blacklist = [], string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    $blacklist = array_merge($blacklist, ['search_api_relevance', 'search_api_random']);
    return implode($delimiter, array_diff_key(
      ($include_sorts ? array_merge($this->allFieldsMapped[$language_id], $this->sortFieldsMapped[$language_id]) : $this->allFieldsMapped[$language_id]),
      array_flip($blacklist))
    );
  }

  /**
   * Formats the list of all Search API fields as a string of Solr field names.
   *
   * @param string $delimiter
   *   (optional) The delimiter to use. Defaults to ",".
   * @param bool $include_sorts
   *   (optional) Whether to include sort fields. Defaults to TRUE.
   * @param array $blacklist
   *   (optional) An array of field names to exclude.
   * @param string $language_id
   *   (optional) The language ID. Defaults to "und".
   *
   * @return string
   *   A list of all Solr field names for the index.
   */
  public function _all_doc_value_fields_list(string $delimiter = ',', bool $include_sorts = TRUE, array $blacklist = [], string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    $blacklist = array_merge($blacklist, ['search_api_relevance', 'search_api_random']);
    return implode($delimiter, array_diff_key(
      // All sort fields have docValues.
      ($include_sorts ? array_merge($this->allDocValueFieldsMapped[$language_id], $this->sortFieldsMapped[$language_id]) : $this->allDocValueFieldsMapped[$language_id]),
      array_flip($blacklist))
    );
  }

  /**
   * Escapes a value to be used in a Solr streaming expression.
   *
   * @param string $value
   *   The string to escape.
   * @param bool $single_term
   *   (optional) Escapes the value as single term if TRUE, otherwise as phrase.
   *   Defaults to TRUE.
   * @param string|null $search_api_field_name
   *   (optional) If provided the method will use it to check for each processor
   *   whether it is supposed to be run on the value. If the name is not
   *   provided, no processor will act on the value.
   *
   * @return string
   *   The escaped value.
   */
  public function _escaped_value(string $value, bool $single_term = TRUE, ?string $search_api_field_name = NULL) {
    if (is_string($value) && $search_api_field_name) {
      foreach ($this->index->getProcessorsByStage(ProcessorInterface::STAGE_PREPROCESS_QUERY) as $processor) {
        if ($processor instanceof SolrProcessorInterface) {
          $configuration = $processor->getConfiguration();
          if (in_array($search_api_field_name, $configuration['fields'])) {
            $value = $processor->encodeStreamingExpressionValue($value) ?: $value;
          }
        }
      }
    }
    $escaped_string = $single_term ?
      $this->queryHelper->escapeTerm($value) :
      $this->queryHelper->escapePhrase($value);
    // If the escaped strings are to be used inside a streaming expression
    // double quotes need to be escaped once more.
    // (e.g. q="field:\"word1 word2\"").
    // See also https://issues.apache.org/jira/browse/SOLR-8409
    $escaped_string = str_replace('"', '\\"', $escaped_string);
    return $escaped_string;
  }

  /**
   * Formats a field and its value to be used in a Solr streaming expression.
   *
   * @param string $search_api_field_name
   *   The Search API field name.
   * @param string $value
   *   The field value.
   * @param string $language_id
   *   (optional) The language ID. Defaults to "und".
   *
   * @return string
   *   The Solr field name and the value as 'field:value'.
   */
  public function _field_value(string $search_api_field_name, string $value, string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    return $this->_field($search_api_field_name, $language_id) . ':' . $value;
  }

  /**
   * Formats a field and its escaped value for a Solr streaming expression.
   *
   * @param string $search_api_field_name
   *   The Search API field name.
   * @param string $value
   *   The field value.
   * @param bool $single_term
   *   (optional) Escapes the value as single term if TRUE, otherwise as phrase.
   *   Defaults to TRUE.
   * @param string $language_id
   *   (optional) The language ID. Defaults to "und".
   *
   * @return string
   *   The Solr field name and the escaped value as 'field:value'.
   */
  public function _field_escaped_value(string $search_api_field_name, string $value, bool $single_term = TRUE, string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    return $this->_field($search_api_field_name, $language_id) . ':' . $this->_escaped_value($value, $single_term, $search_api_field_name);
  }

  /**
   * Calls _escaped_value on each array element and returns the imploded result.
   *
   * @param string $glue
   *   The string to put between the escaped values.
   *   This can be used to create an "or" condition from the array of values,
   *   for example, by passing the string ' || ' as glue.
   * @param array $values
   *   The array of values to escape.
   * @param bool $single_term
   *   (optional) Escapes the value as single term if TRUE, otherwise as phrase.
   *   Defaults to TRUE.
   * @param string|null $search_api_field_name
   *   (optional) Passed on to _escaped_value();
   *   Influences whether processors act on the values.
   *
   * @return string
   *   The imploded string of escaped values.
   */
  public function _escape_and_implode(string $glue, array $values, $single_term = TRUE, ?string $search_api_field_name = NULL) {
    $escaped_values = [];
    foreach ($values as $value) {
      $escaped_values[] = $this->_escaped_value($value, $single_term, $search_api_field_name);
    }
    return implode($glue, $escaped_values);
  }

  /**
   * Rename a field within select().
   *
   * @param string $search_api_field_name_source
   *   The Search API field name to rename.
   * @param string $search_api_field_name_target
   *   The target field name.
   * @param string $language_id
   *   (optional) The language ID. Defaults to "und".
   *
   * @return string
   *   The expression as string.
   */
  public function _select_renamed_field(string $search_api_field_name_source, string $search_api_field_name_target, string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    return $this->_field($search_api_field_name_source, $language_id) . ' as ' . $this->_field($search_api_field_name_target, $language_id);
  }

  /**
   * Copy a field's value to a different field within select().
   *
   * @param string $search_api_field_name_source
   *   The Search API field name to copy.
   * @param string $search_api_field_name_target
   *   The target field name.
   * @param string $language_id
   *   (optional) The language ID. Defaults to "und".
   *
   * @return string
   *   The expression as string.
   */
  public function _select_copied_field(string $search_api_field_name_source, string $search_api_field_name_target, string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    if (version_compare($this->connector->getSolrVersion(), '8.4.1', '>=')) {
      return $this->concat(
          $this->_field($search_api_field_name_source, $language_id)
          // Delimiter must be set but is ignored if just one field is provided.
          . ',delim=","'
        ) . ' as ' . $this->_field($search_api_field_name_target, $language_id);
    }

    return $this->concat(
      'fields="' . $this->_field($search_api_field_name_source, $language_id) . '"',
      // Delimiter must be set but is ignored if just one field is provided.
      'delim=","',
      'as="' . $this->_field($search_api_field_name_target, $language_id) . '"'
    );
  }

  /**
   * Eases intersect() streaming expressions by applying required sorts.
   *
   * @param string $stream1
   *   A streaming expression as string.
   * @param string $stream2
   *   A streaming expression as string.
   * @param string $field
   *   The Search API field name or Solr reserved field name to use for the
   *   intersection.
   * @param string $language_id
   *   (optional) The language ID. Defaults to "und".
   *
   * @return string
   *   A chainable streaming expression as string.
   */
  public function _intersect(string $stream1, string $stream2, string $field, string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    $solr_field = $this->_field($field, $language_id);
    return $this->intersect(
      $this->sort(
        $stream1,
        'by="' . $solr_field . ' ASC"'
      ),
      $this->sort(
        $stream2,
        'by="' . $solr_field . ' ASC"'
      ),
      'on=' . $solr_field
    );
  }

  /**
   * Eases merge() streaming expressions by applying required sorts.
   *
   * @param string $stream1
   *   A streaming expression as string.
   * @param string $stream2
   *   A streaming expression as string.
   * @param string $field
   *   The Search API field name or Solr reserved field name to use for the
   *   intersection.
   * @param string $language_id
   *   (optional) The language ID. Defaults to "und".
   *
   * @return string
   *   A chainable streaming expression as string.
   */
  public function _merge(string $stream1, string $stream2, string $field, string $language_id = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
    $solr_field = $this->_field($field, $language_id);
    return $this->merge(
      $this->sort(
        $stream1,
        'by="' . $solr_field . ' ASC"'
      ),
      $this->sort(
        $stream2,
        'by="' . $solr_field . ' ASC"'
      ),
      'on="' . $solr_field . ' ASC"'
    );
  }

  /**
   * Eases search() streaming expressions if all results are required.
   *
   * Internally this function switches to the /export query type by default. But
   * if you run into errors like "field XY requires DocValues" you should use
   * _search_all().
   *
   * @return string
   *   A chainable streaming expression as string.
   */
  public function _export_all() {
    return $this->search(
      $this->_collection(),
      implode(', ', func_get_args()),
      // Compared to the default query handler, the export query handler
      // doesn't limit the number of results.
      'qt="/export"'
    );
  }

  /**
   * Eases search() streaming expressions if all results are required.
   *
   * Internally this function uses the default /select query type and sets the
   * rows parameter "to be 10000000 or some other ridiculously large value that
   * is higher than the possible number of rows that are expected".
   *
   * @see https://wiki.apache.org/solr/CommonQueryParameters
   * @see https://lucene.apache.org/solr/guide/7_3/stream-source-reference.html
   *
   * @return string
   *   A chainable streaming expression as string.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function _search_all() {
    return $this->search(
      $this->_collection(),
      implode(', ', func_get_args()),
      'rows=' . $this->getSearchAllRows()
    );
  }

  /**
   * Applies the update decorator to the incoming stream.
   *
   * @param string $stream
   *   The stream value.
   * @param array $options
   *   The option keys are the ones from the Solr documentation, prefixed with
   *   "update.".
   *
   * @see https://lucene.apache.org/solr/guide/7_3/stream-decorator-reference.html#update
   *
   * @return string
   *   A chainable streaming expression as string.
   */
  public function _update(string $stream, array $options = []) {
    $options += [
      'update.batchSize' => 500,
    ];
    return $this->update(
      $this->_collection(),
      'batchSize=' . $options['update.batchSize'],
      $stream
    );
  }

  /**
   * Applies the commit decorator to the incoming stream.
   *
   * @param string $stream
   *   The stream value.
   * @param array $options
   *   The option keys are the ones from the Solr documentation, prefixed with
   *   "commit.".
   *
   * @see https://lucene.apache.org/solr/guide/7_3/stream-decorator-reference.html#commit
   *
   * @return string
   *   A chainable streaming expression as string.
   */
  public function _commit(string $stream, array $options = []) {
    $options += [
      'commit.batchSize'    => 0,
      'commit.waitFlush'    => FALSE,
      'commit.waitSearcher' => FALSE,
      'commit.softCommit'   => FALSE,
    ];
    return $this->commit(
      $this->_collection(),
      'batchSize=' . $options['commit.batchSize'],
      'waitFlush=' . ($options['commit.waitFlush'] ? 'true' : 'false'),
      'waitSearcher=' . ($options['commit.waitSearcher'] ? 'true' : 'false'),
      'softCommit=' . ($options['commit.softCommit'] ? 'true' : 'false'),
      $stream
    );
  }

  /**
   * A shorthand for _update() and _commit().
   *
   * @param string $stream
   *   The stream value.
   * @param array $options
   *   (optional) An array of options.
   *
   * @return string
   *   A chainable streaming expression as string.
   */
  public function _commit_update(string $stream, array $options = []) {
    return $this->_commit(
      $this->_update($stream, $options),
      $options
    );
  }

  /**
   * Returns a Solr filter query to limit results to the current index.
   *
   * @return string
   *   The filter query ready to use for the 'fq' parameter.
   */
  public function _index_filter_query() {
    return $this->indexFilterQuery;
  }

  /**
   * Returns the ID of the targeted index.
   *
   * @return string
   *   The index ID.
   */
  public function _index_id() {
    return $this->targetedIndexId;
  }

  /**
   * Returns the Search API Solr Search site hash of the drupal installation.
   *
   * @see Utility::getSiteHash()
   *
   * @return string
   *   The site hash.
   */
  public function _site_hash() {
    return $this->targetedSiteHash;
  }

  /**
   * Returns the formatted date for the current request.
   *
   * @return string
   *   The formatted date.
   */
  public function _request_time() {
    return $this->requestTime;
  }

  /**
   * Returns the timestamp expression for the current request.
   *
   * @return string
   *   The timestamp expression.
   */
  public function _timestamp_value() {
    return 'val(' . $this->requestTime . ') as timestamp';
  }

  /**
   * Eases topic() expressions if there's no specific checkpoint collection.
   *
   * @return string
   *   A chainable streaming expression as string.
   */
  public function _topic() {
    return $this->topic(
      $this->_checkpoints_collection(),
      $this->_collection(),
      'initialCheckpoint=0',
      implode(', ', func_get_args())
    );
  }

  /**
   * Eases topic() expressions if there's no specific checkpoint collection.
   *
   * @return string
   *   A chainable streaming expression as string.
   */
  public function _topic_all() {
    return $this->topic(
      $this->_checkpoints_collection(),
      $this->_collection(),
      'initialCheckpoint=0',
      'rows=' . $this->getSearchAllRows(),
      implode(', ', func_get_args())
    );
  }

  /**
   * Formats a checkpoint parameter for topic() or _topic().
   *
   * The checkpoint name gets suffixed by targeted index and site hash to avoid
   * collisions.
   *
   * @param string $checkpoint
   *
   * @return string
   *   Formatted checkpoint parameter.
   */
  public function _checkpoint($checkpoint) {
    return 'id="' . Utility::formatCheckpointId($checkpoint, $this->targetedIndexId, $this->targetedSiteHash) . '"';
  }

  /**
   * Returns the row limit for _search_all() and _topic_all() expressions.
   *
   * Both need a row limit that matches the real number of documents or higher.
   * To increase the number of query result cache hits the required document
   * counts are "normalized" to the nearest higher power of 2. Setting them to
   * a very high fixed value makes no sense as this would waste memory in Solr
   * Cloud and might lead to out of memory exceptions. The numbers are prepared
   * via search_api_solr_cron(). If the cron hasn't run yet the function return
   * 512 as fallback.
   *
   * @return int
   *   Integer of the row limit.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   *
   * @see search_api_solr_cron()
   */
  public function getSearchAllRows() {
    if (!$this->searchAllRows) {
      $rows = \Drupal::state()->get('search_api_solr.search_all_rows', []);
      $this->searchAllRows = $rows[$this->serverId][$this->targetedSiteHash][$this->targetedIndexId] ?? FALSE;
      if (FALSE === $this->searchAllRows) {
        $counts = $this->backend->getDocumentCounts();
        $this->searchAllRows = $rows[$this->serverId][$this->targetedSiteHash][$this->targetedIndexId] =
          Utility::normalizeMaxRows($counts[$this->targetedSiteHash][$this->targetedIndexId] ?? ($counts['#total'] ?? 512));
        \Drupal::state()->set('search_api_solr.search_all_rows', $rows);
      }
    }
    return $this->searchAllRows;
  }

}
