<?php

namespace Drupal\geolocation;

/**
 * Trait Proximity.
 */
trait ProximityTrait {

  /**
   * Get distance conversion factor.
   *
   * @param string $unit
   *   Optional unit.
   *
   * @return array|float
   *   Either specific factor or all.
   */
  public static function getDistanceConversions($unit = NULL) {
    $conversions = [
      'km' => 1,
      'mi' => 1.609344,
      'nm' => 1.852,
      'm' => 0.001,
      'ly' => 9460753090819,
    ];

    if (!empty($conversions[$unit])) {
      return $conversions[$unit];
    }
    return $conversions;

  }

  /**
   * Convert to/from km.
   *
   * @param float|int $value
   *   Distance value.
   * @param mixed $factor
   *   Factor to convert by. Defaults to mile.
   * @param bool $invert
   *   FALSE converts to, TRUE from km.
   *
   * @return bool|float
   *   Distance in km or target.
   */
  public static function convertDistance($value, $factor = NULL, $invert = FALSE) {
    $value = (float) $value;

    if (empty($factor)) {
      $factor = self::getDistanceConversions('mi');
    }

    if (
      is_string($factor)
      && !empty(self::getDistanceConversions($factor))
    ) {
      $factor = self::getDistanceConversions($factor);
    }

    if (is_numeric($factor)) {
      if ($invert) {
        return (float) $value / $factor;
      }
      return (float) $value * $factor;
    }

    return FALSE;
  }

  /**
   * Gets the query fragment for adding a proximity field to a query.
   *
   * @param string $table_name
   *   The proximity table name.
   * @param string $field_id
   *   The proximity field ID.
   * @param string $filter_lat
   *   The latitude to filter for.
   * @param string $filter_lng
   *   The longitude to filter for.
   *
   * @return string
   *   The fragment to enter to actual query.
   */
  public static function getProximityQueryFragment($table_name, $field_id, $filter_lat, $filter_lng) {

    // Define the field names.
    $field_latsin = "{$table_name}.{$field_id}_lat_sin";
    $field_latcos = "{$table_name}.{$field_id}_lat_cos";
    $field_lng    = "{$table_name}.{$field_id}_lng_rad";

    // deg2rad() is sensitive to empty strings. Replace with integer zero.
    $filter_lat = empty($filter_lat) ? 0 : $filter_lat;
    $filter_lng = empty($filter_lng) ? 0 : $filter_lng;

    // Pre-calculate filter values.
    $filter_latcos = cos(deg2rad($filter_lat));
    $filter_latsin = sin(deg2rad($filter_lat));
    $filter_lng    = deg2rad($filter_lng);

    return "(
      ACOS(LEAST(1,
        $filter_latcos
        * $field_latcos
        * COS( $filter_lng - $field_lng  )
        +
        $filter_latsin
        * $field_latsin
      )) * 6371
    )";
  }

}
