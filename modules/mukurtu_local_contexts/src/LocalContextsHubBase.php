<?php

namespace Drupal\mukurtu_local_contexts;

use Drupal\Core\Database\Connection;

class LocalContextsHubBase {
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
   * The Drupal database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $db;

  /**
   * The Local Contexts API object.
   *
   * @var \Drupal\mukurtu_local_contexts\LocalContextsApi
   */
  protected LocalContextsApi $lcApi;

  /**
   * The HTTP request timestamp.
   *
   * @var int
   */
  protected int $requestTime;

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
    $this->db = \Drupal::database();
    $this->lcApi = new LocalContextsApi();
    $this->requestTime = \Drupal::time()->getRequestTime();
  }
}
