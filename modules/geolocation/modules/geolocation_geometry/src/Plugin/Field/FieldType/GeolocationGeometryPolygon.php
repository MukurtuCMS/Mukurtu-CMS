<?php

namespace Drupal\geolocation_geometry\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Plugin implementation of the 'geolocation' field type.
 *
 * @FieldType(
 *   id = "geolocation_geometry_polygon",
 *   label = @Translation("Geolocation Geometry - Polygon"),
 *   category = "Spatial fields",
 *   description = @Translation("This field stores spatial geometry data."),
 *   default_widget = "geolocation_geometry_wkt",
 *   default_formatter = "geolocation_geometry_wkt"
 * )
 */
class GeolocationGeometryPolygon extends GeolocationGeometryBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $schema['columns']['geometry']['pgsql_type'] = "geometry('POLYGON')";
    $schema['columns']['geometry']['mysql_type'] = 'polygon';

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $reference_point = self::getRandomCoordinates();
    $coordinates = [];
    for ($i = 1; $i <= 16; $i++) {
      $coordinates[] = self::getRandomCoordinates($reference_point);
    }
    $center_point = self::getCenterFromCoordinates($coordinates);
    usort(
      $coordinates,
      function ($a, $b) use ($center_point) {
        return self::sortCoordinatesByAngle($a, $b, $center_point) ? 1 : -1;
      }
    );
    // POLYGONS need to be closed.
    $coordinates[] = $coordinates[0];

    $values['geojson'] = '{
      "type": "Polygon",
      "coordinates": [
        [';
    foreach ($coordinates as $coordinate) {
      $values['geojson'] .= '[' . $coordinate['longitude'] . ', ' . $coordinate['latitude'] . '],';
    }
    $values['geojson'] = rtrim($values['geojson'], ',');
    $values['geojson'] .= ']]}';

    return $values;
  }

}
