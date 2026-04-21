<?php

namespace Drupal\geolocation_address\Plugin\Field\FieldFormatter;

use Drupal\geolocation\Plugin\Field\FieldFormatter\GeolocationMapFormatterBase;

/**
 * Plugin implementation of the 'geolocation' formatter.
 *
 * @FieldFormatter(
 *   id = "geolocation_address",
 *   module = "geolocation",
 *   label = @Translation("Geolocation Address Formatter - Map"),
 *   field_types = {
 *     "address"
 *   }
 * )
 */
class GeolocationAddressFormatter extends GeolocationMapFormatterBase {

  /**
   * {@inheritdoc}
   */
  static protected $dataProviderId = 'geolocation_address_field_provider';

}
