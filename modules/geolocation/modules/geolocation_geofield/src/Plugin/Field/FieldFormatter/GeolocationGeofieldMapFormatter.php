<?php

namespace Drupal\geolocation_geofield\Plugin\Field\FieldFormatter;

use Drupal\geolocation\Plugin\Field\FieldFormatter\GeolocationMapFormatterBase;

/**
 * Plugin implementation of the 'geofield' formatter.
 *
 * @FieldFormatter(
 *   id = "geolocation_geofield",
 *   module = "geolocation",
 *   label = @Translation("Geolocation Geofield Formatter - Map"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class GeolocationGeofieldMapFormatter extends GeolocationMapFormatterBase {

  /**
   * {@inheritdoc}
   */
  static protected $dataProviderId = 'geofield';

}
