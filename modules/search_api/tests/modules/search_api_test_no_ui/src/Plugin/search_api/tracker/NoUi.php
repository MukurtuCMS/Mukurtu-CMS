<?php

namespace Drupal\search_api_test_no_ui\Plugin\search_api\tracker;

use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiTracker;
use Drupal\search_api\Tracker\TrackerPluginBase;

/**
 * Provides a test tracker that should be hidden from the UI.
 */
#[SearchApiTracker(
  id: 'search_api_test_no_ui',
  label: new TranslatableMarkup('No UI tracker'),
  no_ui: TRUE,
)]
class NoUi extends TrackerPluginBase implements PluginFormInterface {
}
