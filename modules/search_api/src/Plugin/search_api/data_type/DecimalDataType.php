<?php

namespace Drupal\search_api\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a decimal data type.
 */
#[SearchApiDataType(
  id: 'decimal',
  label: new TranslatableMarkup('Decimal'),
  description: new TranslatableMarkup('Contains numeric, typically non-integer values.'),
  default: TRUE,
)]
class DecimalDataType extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    $value = (float) $value;
    if (!strpos((string) $value, '.')) {
      return (int) $value;
    }
    return $value;
  }

}
