<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\GeocoderCountryFormatting;

use Drupal\geolocation_leaflet\NominatimRoadFirstFormattingBase;

/**
 * Provides address formatting.
 *
 * @GeocoderCountryFormatting(
 *   id = "nominatim_br",
 *   country_code = "br",
 *   geocoder = "nominatim",
 * )
 */
class Brazil extends NominatimRoadFirstFormattingBase {

  /**
   * {@inheritdoc}
   */
  public function format(array $atomics) {
    $address_elements = parent::format($atomics);

    if ($atomics['state']) {
      $address_elements['administrativeArea'] = $atomics['state'];
    }

    if ($atomics['suburb']) {
      $address_elements['dependentLocality'] = $atomics['suburb'];
      unset($address_elements['addressLine2']);
    }

    return $address_elements;
  }

}
