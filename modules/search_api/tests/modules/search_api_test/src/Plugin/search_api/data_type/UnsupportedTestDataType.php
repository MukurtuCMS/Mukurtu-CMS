<?php

namespace Drupal\search_api_test\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a dummy data type for testing purposes.
 */
#[SearchApiDataType(
  id: 'search_api_test_unsupported',
  label: new TranslatableMarkup('Unsupported test data type'),
  description: new TranslatableMarkup('Unsupported dummy data type implementation')
)]
class UnsupportedTestDataType extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFallbackType() {
    return 'string';
  }

}
