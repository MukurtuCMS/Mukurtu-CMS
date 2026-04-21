<?php

namespace Drupal\geofield\Plugin\views;

/**
 * Generates a GeofieldBoundaryHandlerTrait.
 */
trait GeofieldBoundaryHandlerTrait {

  /**
   * Gets the query fragment for adding a boundary field to a query.
   *
   * @param string $table_name
   *   The proximity table name.
   * @param string $field_id
   *   The proximity field ID.
   * @param string $filter_lat_north_east
   *   The latitude to filter for.
   * @param string $filter_lon_north_east
   *   The longitude to filter for.
   * @param string $filter_lat_south_west
   *   The latitude to filter for.
   * @param string $filter_lon_south_west
   *   The longitude to filter for.
   *
   * @return string
   *   The fragment to enter to actual query.
   */
  public static function getBoundaryQueryFragment($table_name, $field_id, $filter_lat_north_east, $filter_lon_north_east, $filter_lat_south_west, $filter_lon_south_west) {
    // Define the field name.
    $field_lat = "{$table_name}.{$field_id}_lat";
    $field_lon = "{$table_name}.{$field_id}_lon";

    /*
     * Map shows a map, not a globe, therefore it will never flip over
     * the poles, but it will move across -180°/+180° longitude.
     * So latitude will always have north larger than south, but east not
     * necessarily larger than west.
     */
    return "($field_lat BETWEEN $filter_lat_south_west AND $filter_lat_north_east)
      AND
      (
        ($filter_lon_south_west < $filter_lon_north_east AND $field_lon BETWEEN $filter_lon_south_west AND $filter_lon_north_east)
        OR
        (
          $filter_lon_south_west > $filter_lon_north_east AND (
            $field_lon BETWEEN $filter_lon_south_west AND 180 OR $field_lon BETWEEN -180 AND $filter_lon_north_east
          )
        )
      )
    ";
  }

}
