<?php

namespace Drupal\search_api_test\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a dummy data type for testing purposes.
 */
#[SearchApiDataType(
  id: 'search_api_test',
  label: new TranslatableMarkup('&quot;Test&quot; data type'),
  description: new TranslatableMarkup('Dummy <em>data type</em> implementation')
)]
class TestDataType extends DataTypePluginBase {

}
