<?php

namespace Drupal\search_api_solr_legacy\Plugin\SolrConnector;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api_solr\SolrConnector\SolrConnectorPluginBase;
use Solarium\Core\Client\Endpoint;
use Solarium\QueryType\Select\Query\Query;

/**
 * Class Solr36Connector.
 *
 * Extends SolrConnectorPluginBase for Solr 3.6.
 *
 * @package Drupal\sarch_api_solr_legacy\Plugin\SolrConnector
 *
 * @SolrConnector(
 *   id = "solr_36",
 *   label = @Translation("Solr 3.6"),
 *   description = @Translation("Index items using a Solr 3.6 server.")
 * )
 */
class Solr36Connector extends SolrConnectorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'scheme' => 'http',
      'host' => '',
      'port' => 8983,
      'path' => '/',
      // Solr 3.6 doesn't have the core name in the path. But solarium 6 needs
      // it. The period is a workaround that gives us URLs like "solr/./select".
      'core' => '.',
      'skip_schema_check' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['core'] = [
      '#type' => 'value',
      '#value' => '.',
    ];

    $form['path'] = [
      '#type' => 'value',
      '#value' => '/',
    ];

    $form['workarounds']['skip_schema_check'] = [
      '#type' => 'value',
      '#value' => TRUE,
    ];

    $form['advanced']['jts'] = [
      '#type' => 'value',
      '#value' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function search(Query $query, ?Endpoint $endpoint = NULL) {
    $params = $query->getParams();
    if (!isset($params['q.op'])) {
      $query->addParam('q.op', 'OR');
    }

    return parent::search($query, $endpoint);
  }

  /**
   * {@inheritdoc}
   */
  public function pingServer() {
    return $this->pingCore();
  }

  /**
   * {@inheritdoc}
   */
  public function getServerInfo($reset = FALSE) {
    return $this->getCoreInfo($reset);
  }

  /**
   * {@inheritdoc}
   *
   * Solr 3.6 doesn't support JSON which became the default in solarium 7. Force
   * XML format for update queries.
   */
  public function getUpdateQuery() {
    $query = parent::getUpdateQuery();
    $query->setRequestFormat($query::REQUEST_FORMAT_XML);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function reloadCore() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function coreRestGet($path, ?Endpoint $endpoint = NULL) {
    if (preg_match('@^schema/([^/]+)@', $path, $matches)) {
      if ('fieldtypes' === $matches[1]) {
        return ['fieldTypes' => ['name' => 'Solr 3.6']];
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function coreRestPost($path, $command_json = '', ?Endpoint $endpoint = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function serverRestGet($path) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function serverRestPost($path, $command_json = '') {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function alterConfigFiles(array &$files, string $lucene_match_version, string $server_id = '') {
    parent::alterConfigFiles($files, $lucene_match_version, $server_id);
    if (version_compare($lucene_match_version, '4', '<')) {
      if (isset($files['solrconfig.xml'])) {
        $files['solrconfig.xml'] = str_replace('SEARCH_API_SOLR_SOLRCONFIG_INDEX', $files['solrconfig_index.xml'] ?? '', $files['solrconfig.xml']);
        $files['solrconfig.xml'] = str_replace('SEARCH_API_SOLR_SOLRCONFIG_EXTRA', $files['solrconfig_extra.xml'] ?? '', $files['solrconfig.xml']);
        unset($files['solrconfig_index.xml']);
        unset($files['solrconfig_extra.xml']);
      }
      if (isset($files['schema.xml'])) {
        $files['schema.xml'] = str_replace('SEARCH_API_SOLR_SCHEMA_EXTRA_FIELDS', $files['schema_extra_fields.xml'] ?? '', $files['schema.xml']);
        $files['schema.xml'] = str_replace('SEARCH_API_SOLR_SCHEMA_EXTRA_TYPES', $files['schema_extra_types.xml'] ?? '', $files['schema.xml']);
        unset($files['schema_extra_types.xml']);
        unset($files['schema_extra_fields.xml']);
      }
    }

  }

}
