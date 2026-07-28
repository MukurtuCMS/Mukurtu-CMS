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

  /**
   * Indexes a set of translation rows by locale.
   *
   * The hub doesn't identify translations, so there is no stable ID to key
   * on. Locales aren't guaranteed to be unique either (users can have
   * multiple translations with the same or no locale), so each locale (or
   * lack thereof) is suffixed with an occurrence count to keep every
   * translation while still grouping same-locale translations together.
   *
   * @param array $rows
   *   Translation rows, each with at least a 'locale' key.
   *
   * @return array
   *   The rows keyed by a unique locale-based index.
   */
  protected function indexTranslations(array $rows): array {
    $translations = [];
    $bookkeep = [];

    foreach ($rows as $row) {
      if (empty($row['locale'])) {
        $bookkeep['no_locale_count'] = ($bookkeep['no_locale_count'] ?? 0) + 1;
        $translationIndex = strval($bookkeep['no_locale_count']);
      }
      else {
        $bookkeep[$row['locale']] = ($bookkeep[$row['locale']] ?? 0) + 1;
        $translationIndex = $row['locale'] . '-' . $bookkeep[$row['locale']];
      }
      $translations[$translationIndex] = $row;
    }

    return $translations;
  }
}
