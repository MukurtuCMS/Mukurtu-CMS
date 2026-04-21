<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\GeocoderCountryFormatting;

use Drupal\geolocation_google_maps\GoogleCountryFormattingBase;

/**
 * Provides address formatting.
 *
 * @GeocoderCountryFormatting(
 *   id = "google_ch",
 *   country_code = "ch",
 *   geocoder = "google_geocoding_api",
 * )
 */
class Switzerland extends GoogleCountryFormattingBase {

  /**
   * {@inheritdoc}
   */
  public function format(array $atomics) {
    $address_elements = parent::format($atomics);
    if (
      isset($atomics['streetNumber'])
      && $atomics['streetNumber']
      && isset($atomics['route'])
      && $atomics['route']
    ) {
      $address_elements['addressLine1'] = $atomics['route'] . ' ' . $atomics['streetNumber'];
    }

    return $address_elements;
  }

}
