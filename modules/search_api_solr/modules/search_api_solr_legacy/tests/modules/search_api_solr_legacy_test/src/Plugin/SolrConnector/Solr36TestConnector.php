<?php

namespace Drupal\search_api_solr_legacy_test\Plugin\SolrConnector;

use Drupal\search_api_solr\Utility\Utility;
use Drupal\search_api_solr_legacy\Plugin\SolrConnector\Solr36Connector;
use Solarium\Core\Client\Endpoint;
use Solarium\Core\Client\Request;
use Solarium\Core\Client\Response;
use Solarium\Core\Query\QueryInterface;
use Solarium\Core\Query\Result\Result;

/**
 * Solr 36 test connector.
 *
 * @SolrConnector(
 *   id = "solr_36_test",
 *   label = @Translation("Solr 3.6 Test"),
 *   description = @Translation("Index items using a Solr 3.6 server.")
 * )
 */
class Solr36TestConnector extends Solr36Connector {

  /**
   * The Solarium query.
   *
   * @var \Solarium\Core\Query\QueryInterface
   */
  protected static $query;

  /**
   * The Solarium request.
   *
   * @var \Solarium\Core\Client\Request
   */
  protected static $request;

  /**
   * Whether to intercept the query/request or not.
   *
   * @var bool
   */
  protected $intercept = FALSE;

  /**
   * {@inheritdoc}
   */
  public function execute(QueryInterface $query, ?Endpoint $endpoint = NULL) {
    self::$query = $query;

    if ($this->intercept) {
      /** @var \Solarium\Core\Query\AbstractQuery $query */
      return new Result($query, new Response(''));
    }

    return parent::execute($query, $endpoint);
  }

  /**
   * {@inheritdoc}
   */
  public function executeRequest(Request $request, ?Endpoint $endpoint = NULL) {
    self::$request = $request;

    if ($this->intercept) {
      return new Response('');
    }

    return parent::executeRequest($request, $endpoint);
  }

  /**
   * Gets the Solarium query.
   */
  public function getQuery() {
    return self::$query;
  }

  /**
   * Gets the Solarium request.
   */
  public function getRequest() {
    return self::$request;
  }

  /**
   * Gets the Solarium request parameters.
   */
  public function getRequestParams() {
    return Utility::parseRequestParams(self::$request);
  }

  /**
   * Sets the intercept property.
   */
  public function setIntercept(bool $intercept) {
    $this->intercept = $intercept;
  }

  /**
   * Adjust a config for test cases.
   *
   * @param string $config_name
   *   The name of the config.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function adjustBackendConfig($config_name) {
    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable($config_name);
    $backend_config = $config->get('backend_config');
    unset($backend_config['connector_config']['username']);
    unset($backend_config['connector_config']['password']);
    $config->set('backend_config',
      [
        'connector' => 'solr_36_test',
        'connector_config' => [
          'scheme' => 'http',
          'host' => 'localhost',
          'port' => 8983,
          'path' => '/',
          'core' => '.',
        ] + $backend_config['connector_config'],
      ] + $backend_config)
      ->save(TRUE);

    $search_api_server_storage = \Drupal::entityTypeManager()->getStorage('search_api_server');
    $search_api_server_storage->resetCache();

    $search_api_index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $search_api_index_storage->resetCache();
  }

}
