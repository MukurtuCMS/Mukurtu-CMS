<?php

namespace Drupal\geocoder_geofield\Plugin\Field\FieldFormatter;

/**
 * Plugin implementation of the "Geocode GPX" formatter for File fields.
 *
 * @FieldFormatter(
 *   id = "geocoder_geocode_formatter_gpxfile",
 *   label = @Translation("Geocode GPX"),
 *   field_types = {
 *     "file",
 *   },
 *   description =
 *   "Renders valid GPX data from the file content in the chosen format"
 * )
 */
class GpxFileGeocodeFormatter extends GeoPhpGeocodeFormatter {

  /**
   * Unique Geocoder Plugin used by this formatter.
   *
   * @var string
   */
  protected $formatterPlugin = 'gpxfile';

}
