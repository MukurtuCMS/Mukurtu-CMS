<?php

namespace Drupal\geocoder_geofield\Plugin\Field\FieldFormatter;

/**
 * Plugin implementation of the "Geocode KML" formatter for File fields.
 *
 * @FieldFormatter(
 *   id = "geocoder_geocode_formatter_kmlfile",
 *   label = @Translation("Geocode KML"),
 *   field_types = {
 *     "file",
 *   },
 *   description =
 *   "Renders valid KML data from the file content in the chosen format"
 * )
 */
class KmlFileGeocodeFormatter extends GeoPhpGeocodeFormatter {

  /**
   * Unique Geocoder Plugin used by this formatter.
   *
   * @var string
   */
  protected $formatterPlugin = 'kmlfile';

}
