<?php

namespace Drupal\geolocation_geometry\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Plugin implementation of the 'geolocation' field type.
 *
 * @FieldType(
 *   id = "geolocation_geometry_geometrycollection",
 *   label = @Translation("Geolocation Geometry - Geometry Collection"),
 *   category = "Spatial fields",
 *   description = @Translation("This field stores spatial geometry data."),
 *   default_widget = "geolocation_geometry_wkt",
 *   default_formatter = "geolocation_geometry_wkt"
 * )
 */
class GeolocationGeometryGeometryCollection extends GeolocationGeometryBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $schema['columns']['geometry']['pgsql_type'] = "geometry('GEOMETRYCOLLECTION')";
    $schema['columns']['geometry']['mysql_type'] = 'geometrycollection';

    return $schema;
  }

}
