<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\GeocoderCountryFormatting;

use Drupal\geolocation_google_maps\GoogleCountryFormattingBase;

/**
 * Provides address formatting.
 *
 * @GeocoderCountryFormatting(
 *   id = "google_es",
 *   country_code = "es",
 *   geocoder = "google_geocoding_api",
 * )
 */
class Spain extends GoogleCountryFormattingBase {

  /**
   * {@inheritdoc}
   */
  public function format(array $atomics) {
    $address_elements = parent::format($atomics);
    if (
      isset($atomics['county'])
      && $atomics['county']
    ) {
      $address_elements['administrativeArea'] = $atomics['county'];
    }

    return $address_elements;
  }

}
