<?php

namespace Drupal\search_api\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides an integer data type.
 */
#[SearchApiDataType(
  id: 'integer',
  label: new TranslatableMarkup('Integer'),
  description: new TranslatableMarkup('Contains integer values.'),
  default: TRUE,
)]
class IntegerDataType extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    return (int) $value;
  }

}
