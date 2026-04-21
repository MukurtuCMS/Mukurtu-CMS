<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\GeocoderCountryFormatting;

use Drupal\geolocation_google_maps\GoogleCountryFormattingBase;

/**
 * Provides address formatting.
 *
 * @GeocoderCountryFormatting(
 *   id = "google_it",
 *   country_code = "it",
 *   geocoder = "google_geocoding_api",
 * )
 */
class Italy extends GoogleCountryFormattingBase {

  /**
   * {@inheritdoc}
   */
  public function format(array $atomics) {
    $address_elements = parent::format($atomics);
    if (
      isset($atomics['county'])
      && $atomics['county']
    ) {
      $address_elements['administrativeArea'] = $atomics['countyCode'];
    }

    return $address_elements;
  }

}
