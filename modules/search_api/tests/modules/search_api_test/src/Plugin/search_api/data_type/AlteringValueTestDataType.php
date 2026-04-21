<?php

namespace Drupal\search_api_test\Plugin\search_api\data_type;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a dummy data type for testing purposes.
 */
#[SearchApiDataType(
  id: 'search_api_test_altering',
  label: new TranslatableMarkup('Altering test data type'),
  description: new TranslatableMarkup('Altering dummy data type implementation')
)]
class AlteringValueTestDataType extends DataTypePluginBase implements DependentPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    return strlen($value);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return \Drupal::state()->get('search_api_test.data_type.dependencies', []);
  }

}
