<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\GeocoderCountryFormatting;

use Drupal\geolocation_leaflet\NominatimRoadFirstFormattingBase;

/**
 * Provides address formatting.
 *
 * @GeocoderCountryFormatting(
 *   id = "photon_de",
 *   country_code = "de",
 *   geocoder = "photon",
 * )
 */
class PhotonGermany extends NominatimRoadFirstFormattingBase {

  /**
   * {@inheritdoc}
   */
  public function format(array $atomics) {
    $address_elements = parent::format($atomics);
    if (
      empty($address_elements['locality'])
      && !empty($atomics['state'])
    ) {
      $address_elements['locality'] = $atomics['state'];
    }
    return $address_elements;
  }

}
