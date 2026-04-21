<?php

namespace Drupal\geocoder_geofield\Plugin\Field\FieldFormatter;

/**
 * Plugin implementation of the "Geocode GeoJson" formatter for File fields.
 *
 * @FieldFormatter(
 *   id = "geocoder_geocode_formatter_geojsonfile",
 *   label = @Translation("Geocode GeoJson"),
 *   field_types = {
 *     "file",
 *   },
 *   description =
 *   "Renders valid GeoJson data from the file content in the chosen format"
 * )
 */
class GeoJsonFileGeocodeFormatter extends GeoPhpGeocodeFormatter {

  /**
   * Unique Geocoder Plugin used by this formatter.
   *
   * @var string
   */
  protected $formatterPlugin = 'geojsonfile';

}
