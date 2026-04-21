<?php

namespace Drupal\geolocation;

/**
 * Trait Boundary.
 */
trait BoundaryTrait {

  /**
   * Gets the query fragment for adding a boundary field to a query.
   *
   * @param string $table_name
   *   The proximity table name.
   * @param string $field_id
   *   The proximity field ID.
   * @param string $filter_lat_north_east
   *   The latitude to filter for.
   * @param string $filter_lng_north_east
   *   The longitude to filter for.
   * @param string $filter_lat_south_west
   *   The latitude to filter for.
   * @param string $filter_lng_south_west
   *   The longitude to filter for.
   *
   * @return string
   *   The fragment to enter to actual query.
   */
  public static function getBoundaryQueryFragment($table_name, $field_id, $filter_lat_north_east, $filter_lng_north_east, $filter_lat_south_west, $filter_lng_south_west) {
    // Define the field name.
    $field_lat = "{$table_name}.{$field_id}_lat";
    $field_lng = "{$table_name}.{$field_id}_lng";

    /*
     * Map shows a map, not a globe. Therefore it will never flip over
     * the poles, but it will move across -180°/+180° longitude.
     * So latitude will always have north larger than south, but east not
     * necessarily larger than west.
     */
    return "($field_lat BETWEEN $filter_lat_south_west AND $filter_lat_north_east)
      AND
      (
        ($filter_lng_south_west < $filter_lng_north_east AND $field_lng BETWEEN $filter_lng_south_west AND $filter_lng_north_east)
        OR
        (
          $filter_lng_south_west > $filter_lng_north_east AND (
            $field_lng BETWEEN $filter_lng_south_west AND 180 OR $field_lng BETWEEN -180 AND $filter_lng_north_east
          )
        )
      )
    ";
  }

}
