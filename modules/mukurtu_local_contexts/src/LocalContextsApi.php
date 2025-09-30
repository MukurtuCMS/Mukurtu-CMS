<?php

namespace Drupal\mukurtu_local_contexts;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;

class LocalContextsApi {
  /**
   * The settings configuration key.
   *
   * @var string
   */
  const SETTINGS_CONFIG_KEY = 'mukurtu_local_contexts.settings';

  /**
   * The default local contexts hub URL to use if one has not been set.
   *
   * @var string
   */
  const DEFAULT_HUB_URL = 'https://sandbox.localcontextshub.org/api/v2/';

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * HTTP client for making API requests.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The API endpoint URL.
   *
   * @var string
   */
  protected string $endpointUrl;

  /**
   * @var string Any error message from the API.
   */
  protected string $errorMessage;

  /**
   * Constructs a LocalContextsHubManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct() {
    $this->configFactory = \Drupal::service('config.factory');
    $this->httpClient = \Drupal::httpClient();
    $endpointUrl = $this->configFactory->get(self::SETTINGS_CONFIG_KEY)->get('hub_endpoint') ?? self::DEFAULT_HUB_URL;

    if (str_ends_with($endpointUrl, '/')) {
      $endpointUrl = substr($endpointUrl, 0, -1);
    }
    $this->endpointUrl = $endpointUrl;
    $this->errorMessage = '';
  }

  /**
   * Make a call to the API.
   *
   * @param string $path
   *   The API method.
   * @param string $api_key
   *   The API key.
   *
   * @return array
   *   The result of the API call.
   */
  public function makeRequest($path, $api_key, ?array $query_params = []) {
    $options = [
      // The hub uses 301 redirects.
      'allow_redirects' => TRUE,
      // Don't throw exceptions for non-200 responses.
      'http_errors' => FALSE,
      'headers' => [
        // Set the authorization header.
        'X-Api-Key' => $api_key,
        'Accept' => 'application/json',
      ],
      'query' => $query_params,
      'connect_timeout' => 5,
    ];
    $request = new $this->httpClient($options);

    if (!str_starts_with($path, '/')) {
      $path = '/' . $path;
    }
    $url = $this->endpointUrl . $path;
    $result = [];

    try {
      // Send the request.
      $response = $request->request('GET', $url);
      $http_code = $response->getStatusCode();
      $result = json_decode($response->getBody()->getContents(), TRUE);
      if ($http_code != 200) {
        $this->errorMessage = $result['detail'] ?? 'Unknown error.';
      }
    }
    catch (RequestException|ConnectException $e) {
      $this->errorMessage = $e->getMessage();
    }

    return $result;
  }

  /**
   * Load all the pages of a multipage API response.
   *
   * @param string $path
   *   The API method.
   * @param string $api_key
   *   The API key.
   *
   * @return array
   *   The result of all the combined API calls.
   */
  public function makeMultipageRequest($path, $api_key, ?array $query_params = []) {
    $results = [];
    $more_pages = TRUE;
    $max_pages = 20;
    // 1-indexed page numbers. A page number of 0 will return "Invalid page."
    $page_number = 1;
    while ($more_pages) {
      $query_params['page'] = $page_number;
      $response = $this->makeRequest($path, $api_key, $query_params);
      if (!isset($response['results'])) {
        break;
      }
      $results = array_merge($results, $response['results']);
      $more_pages = !empty($response['next']);
      $page_number++;
      if ($page_number > $max_pages) {
        $more_pages = FALSE;
      }
    }
    return $results;
  }

  /**
   * Validate an API key as valid.
   *
   * @param string $api_key
   *   The API key.
   *
   * @return bool
   */
  public function validateApiKey($api_key) {
    $this->errorMessage = '';
    $this->makeRequest('/projects', $api_key);
    return !$this->errorMessage;
  }

  /**
   * Returns any error messages that may have occurred during fetchFromHub().
   *
   * @return string
   */
  public function getErrorMessage() {
    return $this->errorMessage;
  }

}
