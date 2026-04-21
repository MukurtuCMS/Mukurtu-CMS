<?php

namespace Drupal\geolocation_geometry;

/**
 * Trait Geometry Boundary.
 */
trait GeometryBoundaryTrait {

  /**
   * Gets the query fragment for adding a boundary field to a query.
   *
   * @param string $table_name
   *   The proximity table name.
   * @param string $field_id
   *   The proximity field ID.
   * @param string $placeholder
   *   Placeholder SQL.
   *
   * @return string
   *   The fragment to enter to actual query.
   */
  public static function getGeometryBoundaryQueryFragment(string $table_name, string $field_id, string $placeholder): string {
    // Define the field name.
    $field_point = "{$table_name}.{$field_id}_geometry";

    /*
     * Google Maps shows a map, not a globe. Therefore it will never flip over
     * the poles, but it will move across -180°/+180° longitude.
     * So latitude will always have north larger than south, but east not
     * necessarily larger than west.
     */
    return 'ST_Contains(
      ST_GeomFromGeoJSON(' . $placeholder . '),
      ' . $field_point . '
    )';
  }

  /**
   * Gets the value placeholder.
   *
   * @param string $placeholder
   *   SQL placeholder.
   * @param string|float $filter_lat_north_east
   *   The latitude to filter for.
   * @param string|float $filter_lng_north_east
   *   The longitude to filter for.
   * @param string|float $filter_lat_south_west
   *   The latitude to filter for.
   * @param string|float $filter_lng_south_west
   *   The longitude to filter for.
   *
   * @return array
   *   Placeholder array.
   */
  public static function getGeometryBoundaryQueryValue(string $placeholder, $filter_lat_north_east, $filter_lng_north_east, $filter_lat_south_west, $filter_lng_south_west): array {
    return [
      $placeholder  => '{"type": "Polygon","coordinates": [[
        [' . $filter_lng_south_west . ', ' . $filter_lat_south_west . '],
        [' . $filter_lng_north_east . ', ' . $filter_lat_south_west . '],
        [' . $filter_lng_north_east . ', ' . $filter_lat_north_east . '],
        [' . $filter_lng_south_west . ', ' . $filter_lat_north_east . '],
        [' . $filter_lng_south_west . ', ' . $filter_lat_south_west . ']
      ]]}',
    ];
  }

}
