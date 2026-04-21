<?php

namespace Drupal\search_api_test_no_ui\Plugin\search_api\parse_mode;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiParseMode;
use Drupal\search_api\ParseMode\ParseModePluginBase;

/**
 * Provides a parse mode that should be hidden from the UI.
 */
#[SearchApiParseMode(
  id: 'search_api_test_no_ui',
  label: new TranslatableMarkup('No UI parse mode'),
  no_ui: TRUE,
)]
class NoUi extends ParseModePluginBase {

  /**
   * {@inheritdoc}
   */
  public function parseInput($keys) {
    return $keys;
  }

}
