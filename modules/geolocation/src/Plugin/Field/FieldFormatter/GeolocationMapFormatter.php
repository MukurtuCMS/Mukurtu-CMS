<?php

namespace Drupal\geolocation\Plugin\Field\FieldFormatter;

/**
 * Plugin implementation of the 'geolocation' formatter.
 *
 * @FieldFormatter(
 *   id = "geolocation_map",
 *   module = "geolocation",
 *   label = @Translation("Geolocation Formatter - Map"),
 *   field_types = {
 *     "geolocation"
 *   }
 * )
 */
class GeolocationMapFormatter extends GeolocationMapFormatterBase {

  /**
   * {@inheritdoc}
   */
  static protected $dataProviderId = 'geolocation_field_provider';

}
