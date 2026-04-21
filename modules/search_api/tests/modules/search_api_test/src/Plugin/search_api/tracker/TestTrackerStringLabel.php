<?php

namespace Drupal\search_api_test\Plugin\search_api\tracker;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiTracker;

/**
 * Provides a test tracker plugin.
 */
#[SearchApiTracker(
  id: 'search_api_test_string_label',
  label: new TranslatableMarkup('&amp;quot;String label&amp;quot; test tracker'),
  description: new TranslatableMarkup('This is the <em>test tracker with string label</em> plugin description.'),
)]
class TestTrackerStringLabel extends TestTracker {
}
