<?php

namespace Drupal\search_api_test_no_ui\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a test data type that should be hidden from the UI.
 */
#[SearchApiDataType(
  id: 'search_api_test_no_ui',
  label: new TranslatableMarkup('No UI data type'),
  no_ui: TRUE,
)]
class NoUi extends DataTypePluginBase {
}
