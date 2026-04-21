<?php

namespace Drupal\search_api\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a boolean data type.
 */
#[SearchApiDataType(
  id: 'boolean',
  label: new TranslatableMarkup('Boolean'),
  description: new TranslatableMarkup('Boolean fields can only have one of two values: true or false.'),
  default: TRUE,
)]
class BooleanDataType extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    return (bool) $value;
  }

}
