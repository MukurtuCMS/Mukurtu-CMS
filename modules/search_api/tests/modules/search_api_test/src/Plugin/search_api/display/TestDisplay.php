<?php

namespace Drupal\search_api_test\Plugin\search_api\display;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDisplay;
use Drupal\search_api\Display\DisplayPluginBase;

/**
 * Provides a test display.
 */
#[SearchApiDisplay(
  id: 'search_api_test',
  label: new TranslatableMarkup('Test processor'),
  index: 'search_api_test'
)]
class TestDisplay extends DisplayPluginBase {

}
