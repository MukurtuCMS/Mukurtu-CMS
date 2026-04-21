<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\GeocoderCountryFormatting;

use Drupal\geolocation_leaflet\NominatimRoadFirstFormattingBase;

/**
 * Provides address formatting.
 *
 * @GeocoderCountryFormatting(
 *   id = "photon_it",
 *   country_code = "it",
 *   geocoder = "photon",
 * )
 */
class PhotonItaly extends NominatimRoadFirstFormattingBase {

  /**
   * {@inheritdoc}
   */
  public function format(array $atomics) {
    $address_elements = parent::format($atomics);
    if (\Drupal::hasService('address.subdivision_repository')) {
      $subdivisions = \Drupal::service('address.subdivision_repository')->getList(['it']);
      if (!empty($atomics['county']) && ($administrative_area = array_search($atomics['county'], $subdivisions)) !== FALSE) {
        $address_elements['administrativeArea'] = $administrative_area;
      }
      if (
        empty($address_elements['locality'])
        && !empty($atomics['state'])
      ) {
        $address_elements['locality'] = $atomics['state'];
      }
    }
    return $address_elements;
  }

}
