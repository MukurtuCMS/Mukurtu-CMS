<?php

namespace Drupal\search_api_solr_test\Plugin\SolrConnector;

use Drupal\search_api_solr\Plugin\SolrConnector\BasicAuthSolrConnector;
use Drupal\search_api_solr\Utility\Utility;
use Solarium\Core\Client\Endpoint;
use Solarium\Core\Client\Request;
use Solarium\Core\Client\Response;
use Solarium\Core\Query\QueryInterface;
use Solarium\Core\Query\Result\Result;

/**
 * Basic auth Solr test connector.
 *
 * @SolrConnector(
 *   id = "basic_auth_test",
 *   label = @Translation("Basic Auth Test"),
 *   description = @Translation("A connector usable for Solr installations protected by basic authentication.")
 * )
 */
class BasicAuthTestSolrConnector extends BasicAuthSolrConnector {

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

}
