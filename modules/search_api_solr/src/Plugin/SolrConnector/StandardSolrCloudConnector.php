<?php

namespace Drupal\search_api_solr\Plugin\SolrConnector;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrCloudConnectorInterface;
use Drupal\search_api_solr\SolrConnector\SolrConnectorPluginBase;
use Drupal\search_api_solr\Utility\Utility;
use Solarium\Core\Client\Endpoint;
use Solarium\Core\Client\State\ClusterState;
use Solarium\Exception\HttpException;
use Solarium\Exception\OutOfBoundsException;
use Solarium\QueryType\Graph\Query as GraphQuery;
use Solarium\QueryType\Ping\Query as PingQuery;
use Solarium\QueryType\Stream\Query as StreamQuery;

/**
 * Standard Solr Cloud connector.
 *
 * @SolrConnector(
 *   id = "solr_cloud",
 *   label = @Translation("Solr Cloud"),
 *   description = @Translation("A standard connector for a Solr Cloud.")
 * )
 */
class StandardSolrCloudConnector extends SolrConnectorPluginBase implements SolrCloudConnectorInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'checkpoints_collection' => '',
      'stats_cache' => 'org.apache.solr.search.stats.LRUStatsCache',
      'distrib' => TRUE,
      'context' => 'solr',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $configuration['distrib'] = (bool) $configuration['distrib'];

    parent::setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['host']['#title'] = $this->t('Solr node');
    $form['host']['#description'] = $this->t('The host name or IP of a Solr node, e.g. <code>localhost</code> or <code>www.example.com</code>.');

    $form['path']['#description'] = $this->t('The path that identifies the Solr instance to use on the node.');

    $form['core']['#title'] = $this->t('Default Solr collection');
    $form['core']['#description'] = $this->t('The name that identifies the Solr default collection to use. The concrete collection to use could be overwritten per index. The most common use-case is to leverage this default collection. Only sophisticated multi-site setups or foreign indexes accessed via Solr Document Datasources or specific requirements of your Solr hosting provider might require to leave this field empty. Because of that, the field is not marked as required as it should be for most use-cases.');
    $form['core']['#required'] = FALSE;

    $form['timeout']['#description'] = $this->t('The timeout in seconds for search queries sent to the Solr collection.');

    $form['index_timeout']['#description'] = $this->t('The timeout in seconds for indexing requests to the Solr collection.');

    $form['optimize_timeout']['#description'] = $this->t('The timeout in seconds for background index optimization queries on the Solr collection.');

    $form['context'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Solr host context'),
      '#description' => $this->t('The context path for the Solr web application. Defaults to "solr" in any Solr Cloud installation.'),
      '#default_value' => $this->configuration['context'] ?? 'solr',
    ];

    $form['advanced']['checkpoints_collection'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Checkpoints Collection'),
      '#description' => $this->t("The collection where topic checkpoints are stored. Not required if you don't work with topic() streaming expressions."),
      '#default_value' => $this->configuration['checkpoints_collection'] ?? '',
    ];

    $form['advanced']['stats_cache'] = [
      '#type' => 'select',
      '#title' => $this->t('StatsCache'),
      '#options' => [
        'org.apache.solr.search.stats.LocalStatsCache' => $this->t('LocalStatsCache'),
        'org.apache.solr.search.stats.ExactStatsCache' => $this->t('ExactStatsCache'),
        'org.apache.solr.search.stats.ExactSharedStatsCache' => $this->t('ExactSharedStatsCache'),
        'org.apache.solr.search.stats.LRUStatsCache' => $this->t('LRUStatsCache'),
      ],
      '#description' => $this->t('Document and term statistics are needed in order to calculate relevancy. Solr provides four implementations out of the box when it comes to document stats calculation. LocalStatsCache: This only uses local term and document statistics to compute relevance. In cases with uniform term distribution across shards, this works reasonably well. ExactStatsCache: This implementation uses global values (across the collection) for document frequency. ExactSharedStatsCache: This is exactly like the exact stats cache in its functionality but the global stats are reused for subsequent requests with the same terms. LRUStatsCache: This implementation uses an LRU cache to hold global stats, which are shared between requests. Formerly a limitation was that TF/IDF relevancy computations only used shard-local statistics. This is still the case by default or if LocalStatsCache is used. If your data isn’t randomly distributed, or if you want more exact statistics, then remember to configure the ExactStatsCache (or "better").'),
      '#default_value' => $this->configuration['stats_cache'] ?? 'org.apache.solr.search.stats.LRUStatsCache',
    ];

    $form['advanced']['distrib'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Distribute queries'),
      '#description' => $this->t("Normally queries should be distributed across all nodes of a Solr Cloud that store shards of the collection. In rare debug use-cases or when you only run a single node it might be useful to disable the query distribution."),
      '#default_value' => $this->configuration['distrib'] ?? TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function isCloud() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatsSummary() {
    $summary = parent::getStatsSummary();
    $summary['@collection_name'] = '';

    $query = $this->solr->createPing();
    $query->setResponseWriter(PingQuery::WT_PHPS);
    $query->setHandler('admin/mbeans?stats=true');
    $stats = $this->execute($query)->getData();
    if (!empty($stats)) {
      $solr_version = $this->getSolrVersion(TRUE);
      if (version_compare($solr_version, '7.0', '>=')) {
        $summary['@collection_name'] = $stats['solr-mbeans']['CORE']['core']['stats']['CORE.collection'] ?? '';
      }
      else {
        $summary['@core_name'] = $stats['solr-mbeans']['CORE']['core']['stats']['collection'] ?? '';
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionName() {
    return $this->configuration['core'];
  }

  /**
   * {@inheritdoc}
   */
  public function setCollectionNameFromEndpoint(Endpoint $endpoint) {
    $this->configuration['core'] = $endpoint->getCollection() ?? $endpoint->getCore();
  }

  /**
   * {@inheritdoc}
   */
  public function getCheckpointsCollectionName() {
    return $this->configuration['checkpoints_collection'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCheckpointsCollectionEndpoint(): ?Endpoint {
    $checkpoints_collection = $this->getCheckpointsCollectionName();
    if ($checkpoints_collection) {
      try {
        return $this->getEndpoint($checkpoints_collection);
      }
      catch (OutOfBoundsException $e) {
        $additional_config['core'] = $checkpoints_collection;
        return $this->createEndpoint($checkpoints_collection, $additional_config);
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteCheckpoints(string $index_id, string $site_hash) {
    if ($checkpoints_collection_endpoint = $this->getCheckpointsCollectionEndpoint()) {
      $update_query = $this->getUpdateQuery();
      // id:/.*-INDEX_ID-SITE_HASH/ is a regex.
      $update_query->addDeleteQuery('id:/' . Utility::formatCheckpointId('.*', $index_id, $site_hash) . '/');
      $this->update($update_query, $checkpoints_collection_endpoint);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionLink() {
    return $this->getCoreLink();
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionInfo($reset = FALSE) {
    return $this->getCoreInfo($reset);
  }

  /**
   * {@inheritdoc}
   */
  public function getClusterStatus(?string $collection = NULL): ?ClusterState {
    $this->connect();
    $this->useTimeout(self::INDEX_TIMEOUT);

    try {
      $collection = $collection ?? $this->configuration['core'];

      $query = $this->solr->createCollections();
      $action = $query->createClusterStatus();
      $action->setCollection($this->configuration['core']);
      $query->setAction($action);

      $response = $this->solr->collections($query);
      return $response->getWasSuccessful() ? $response->getClusterState() : NULL;
    }
    catch (HttpException $e) {
      throw new SearchApiSolrException(sprintf('Get ClusterStatus for collection %s failed with error code %s: %s', $collection, $e->getCode(), $e->getMessage()), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSetName(): ?string {
    try {
      if ($clusterState = $this->getClusterStatus()) {
        return $clusterState->getCollectionState($this->configuration['core'])->getConfigName();
      }
    }
    catch (\Exception $e) {
      $this->getLogger()->debug('@exception', ['@exception' => $e->getMessage()]);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function uploadConfigset(string $name, string $filename): bool {
    $this->connect();
    $this->useTimeout(self::FINALIZE_TIMEOUT);

    try {
      $configsetsQuery = $this->solr->createConfigsets();
      $action = $configsetsQuery->createUpload();
      $action
        ->setFile($filename)
        ->setName($name)
        ->setOverwrite(TRUE);
      $configsetsQuery->setAction($action);
      $response = $this->solr->configsets($configsetsQuery);
      return $response->getWasSuccessful();
    }
    catch (HttpException $e) {
      throw new SearchApiSolrException(sprintf('Configset upload failed with error code %s: %s', $e->getCode(), $e->getMessage()), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function pingCollection() {
    return parent::pingCore(['distrib' => FALSE]);
  }

  /**
   * {@inheritdoc}
   */
  public function pingCore(array $options = []) {
    return parent::pingCore(['distrib' => TRUE]);
  }

  /**
   * {@inheritdoc}
   */
  public function getStreamQuery() {
    $this->connect();
    return $this->solr->createStream();
  }

  /**
   * {@inheritdoc}
   */
  public function stream(StreamQuery $query, ?Endpoint $endpoint = NULL) {
    $this->useTimeout(self::QUERY_TIMEOUT, $endpoint);
    return $this->execute($query, $endpoint);
  }

  /**
   * {@inheritdoc}
   */
  public function getGraphQuery() {
    $this->connect();
    return $this->solr->createGraph();
  }

  /**
   * {@inheritdoc}
   */
  public function graph(GraphQuery $query, ?Endpoint $endpoint = NULL) {
    $this->useTimeout(self::QUERY_TIMEOUT, $endpoint);
    return $this->execute($query, $endpoint);
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectQuery() {
    $query = parent::getSelectQuery();
    return $query->setDistrib($this->configuration['distrib'] ?? TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getMoreLikeThisQuery() {
    $query = parent::getMoreLikeThisQuery();
    return $query->setDistrib($this->configuration['distrib'] ?? TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getTermsQuery() {
    $query = parent::getTermsQuery();
    return $query->setDistrib($this->configuration['distrib'] ?? TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getSpellcheckQuery() {
    $query = parent::getSpellcheckQuery();
    return $query->setDistrib($this->configuration['distrib'] ?? TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggesterQuery() {
    $query = parent::getSuggesterQuery();
    return $query->setDistrib($this->configuration['distrib'] ?? TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getAutocompleteQuery() {
    $query = parent::getAutocompleteQuery();
    return $query->setDistrib($this->configuration['distrib'] ?? TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function reloadCore() {
    return $this->reloadCollection();
  }

  /**
   * {@inheritdoc}
   */
  public function reloadCollection(?string $collection = NULL): bool {
    $this->connect();
    $this->useTimeout(self::INDEX_TIMEOUT);

    try {
      $collection = $collection ?? $this->configuration['core'];

      $query = $this->solr->createCollections();
      $action = $query->createReload(['name' => $collection]);
      $query->setAction($action);

      $response = $this->solr->collections($query);
      return $response->getWasSuccessful();
    }
    catch (HttpException $e) {
      throw new SearchApiSolrException("Reloading collection $collection failed with error code " . $e->getCode() . ': ' . $e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection(array $options, ?string $collection = NULL): bool {
    $this->connect();
    $this->useTimeout(self::FINALIZE_TIMEOUT);

    try {
      $collection = $collection ?? $this->configuration['core'];

      $query = $this->solr->createCollections();
      $action = $query->createCreate(['name' => $collection] + $options);
      $query->setAction($action);

      $response = $this->solr->collections($query);
      return $response->getWasSuccessful();
    }
    catch (HttpException $e) {
      throw new SearchApiSolrException("Creating collection $collection failed with error code " . $e->getCode() . ': ' . $e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteCollection(?string $collection = NULL): bool {
    $this->connect();
    $this->useTimeout(self::INDEX_TIMEOUT);

    try {
      $collection = $collection ?? $this->configuration['core'];

      $query = $this->solr->createCollections();
      $action = $query->createDelete(['name' => $collection]);
      $query->setAction($action);

      $response = $this->solr->collections($query);
      return $response->getWasSuccessful();
    }
    catch (HttpException $e) {
      throw new SearchApiSolrException("Deleting collection $collection failed with error code " . $e->getCode() . ': ' . $e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterConfigFiles(array &$files, string $lucene_match_version, string $server_id = '') {
    parent::alterConfigFiles($files, $lucene_match_version, $server_id);

    // Leverage the implicit Solr request handlers with default settings for
    // Solr Cloud.
    // @see https://lucene.apache.org/solr/guide/8_0/implicit-requesthandlers.html
    if (version_compare($this->getSolrMajorVersion(), '7', '>=')) {
      $files['solrconfig_extra.xml'] = preg_replace("@<requestHandler\s+name=\"/replication\".*?</requestHandler>@ms", '', $files['solrconfig_extra.xml']);
      $files['solrconfig_extra.xml'] = preg_replace("@<requestHandler\s+name=\"/get\".*?</requestHandler>@ms", '', $files['solrconfig_extra.xml']);
    }
    else {
      $files['solrconfig.xml'] = preg_replace("@<requestHandler\s+name=\"/replication\".*?</requestHandler>@ms", '', $files['solrconfig.xml']);
      $files['solrconfig.xml'] = preg_replace("@<requestHandler\s+name=\"/get\".*?</requestHandler>@ms", '', $files['solrconfig.xml']);
    }

    // Set the StatsCache.
    // @see https://lucene.apache.org/solr/guide/8_0/distributed-requests.html#configuring-statscache-distributed-idf
    if (!empty($this->configuration['stats_cache'])) {
      $files['solrconfig_extra.xml'] .= '<statsCache class="' . $this->configuration['stats_cache'] . '" />' . "\n";
    }

    if (!empty($this->configuration['solr_install_dir'])) {
      $files['solrconfig.xml'] = preg_replace("/{solr\.install\.dir:[^}]*}/", '{solr.install.dir:' . $this->configuration['solr_install_dir'] . '}', $files['solrconfig.xml']);
    }
  }

}
