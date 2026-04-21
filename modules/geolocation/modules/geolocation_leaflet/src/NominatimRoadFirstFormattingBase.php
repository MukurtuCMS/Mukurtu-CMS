<?php

namespace Drupal\geolocation_leaflet;

/**
 * Alternative street formatting base class.
 *
 * Base class for nominatim geocoder country formatting plugins which use the
 * "<road> <house number>" address format.
 */
class NominatimRoadFirstFormattingBase extends NominatimCountryFormattingBase {

  /**
   * {@inheritdoc}
   */
  public function format(array $atomics) {
    $address_elements = parent::format($atomics);
    if (!empty($atomics['road'])) {
      $address_elements['addressLine1'] = $atomics['road'];
    }

    if (!empty($atomics['houseNumber'])) {
      $address_elements['addressLine1'] .= ' ' . $atomics['houseNumber'];
    }

    return $address_elements;
  }

}
