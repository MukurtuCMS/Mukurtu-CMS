<?php

namespace Drupal\geolocation_geometry;

/**
 * Trait GeometryProximity.
 */
trait GeometryProximityTrait {

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
  public static function getGeometryProximityQueryFragment($table_name, $field_id, $filter_lat, $filter_lng) {

    // Define the field name.
    $field_point = "{$table_name}.{$field_id}_geometry";

    // deg2rad() is sensitive to empty strings. Replace with integer zero.
    $filter_lat = empty($filter_lat) ? '0' : $filter_lat;
    $filter_lng = empty($filter_lng) ? '0' : $filter_lng;

    return 'ST_Distance_Sphere(ST_GeomFromText(\'POINT(' . $filter_lng . ' ' . $filter_lat . ')\'), ST_GeomFromText(CONCAT(\'POINT(\', ST_Y(' . $field_point . '), \' \', ST_X(' . $field_point . '), \')\')))/1000';
  }

}
