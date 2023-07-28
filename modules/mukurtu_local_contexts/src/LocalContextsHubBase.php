<?php

namespace Drupal\mukurtu_local_contexts;

class LocalContextsHubBase {
  /**
   * The settings configuration key.
   *
   * @var string
   */
  const SETTINGS_CONFIG_KEY = 'mukurtu_local_contexts.settings';

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The API endpoint URL.
   *
   * @var string
   */
  protected $endpointUrl;

  protected $db;

  /**
   * Constructs a LocalContextsHubManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct()
  {
    $this->configFactory = \Drupal::service('config.factory');
    $this->db = \Drupal::database();
    $endpointUrl = $this->configFactory->get(self::SETTINGS_CONFIG_KEY)->get('hub_endpoint') ?? 'https://anth-ja77-lc-dev-42d5.uc.r.appspot.com/api/v1/';

    if (str_ends_with($endpointUrl, '/')) {
      $endpointUrl = substr($endpointUrl, 0, -1);
    }
    $this->endpointUrl = $endpointUrl;
  }

  /**
   * Make a call to the API.
   *
   * @param string $path
   *   The API method.
   *
   * @return mixed
   *   The result of the API call.
   */
  protected function get($path) {
    $curl = curl_init();

    if (!str_starts_with($path, '/')) {
      $path = '/' . $path;
    }

    $url = $this->endpointUrl . $path;

    curl_setopt($curl, CURLOPT_URL, $url);

    // The hub uses 301 redirects.
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

    // Send the request.
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($http_code == '200') {
      $result = json_decode($response, TRUE);
    } else {
      $result = [];
    }

    curl_close($curl);

    return $result;
  }

}
