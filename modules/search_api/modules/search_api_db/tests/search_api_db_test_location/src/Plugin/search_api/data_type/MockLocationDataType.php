<?php

namespace Drupal\search_api_db_test_location\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a mock location data type for ease of testing.
 *
 * Mostly copied from the search_api_location module.
 */
#[SearchApiDataType(
  id: 'location',
  label: new TranslatableMarkup('Mock Latitude/Longitude'),
  description: new TranslatableMarkup('Mock location data type implementation.'),
)]
class MockLocationDataType extends DataTypePluginBase {

  /**
   * Converts a field value to match the data type (if needed).
   *
   * Only accepts values of this format: POINT(Longitude Latitude)
   *
   * @param mixed $value
   *   The value to convert.
   *
   * @return mixed
   *   The converted value.
   */
  public function getValue($value) {
    $matches = [];
    $is_point = preg_match('#point\((?P<lon>[+-]?[.\d]+) (?P<lat>[+-]?[.\d]+)\)#i', $value, $matches);

    if ($is_point) {
      $lon = $matches['lon'];
      $lat = $matches['lat'];

      return "$lat,$lon";
    }
    else {
      return $value;
    }
  }

}
