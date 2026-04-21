<?php

namespace Drupal\search_api\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a string data type.
 */
#[SearchApiDataType(
  id: 'string',
  label: new TranslatableMarkup('String'),
  description: new TranslatableMarkup('String fields are used for short, keyword-like character strings where you only want to find complete field values, not individual words.'),
  default: TRUE,
)]
class StringDataType extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    return (string) $value;
  }

}
