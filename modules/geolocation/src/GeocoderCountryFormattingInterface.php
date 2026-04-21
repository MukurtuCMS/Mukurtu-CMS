<?php

namespace Drupal\geolocation;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for geolocation geocoder country  plugins.
 */
interface GeocoderCountryFormattingInterface extends PluginInspectionInterface {

  /**
   * Reverse geocode an address.
   *
   * Intended return subject to available data:
   *
   * @code
   * [
   *   'organization',
   *   'address_line1',
   *   'address_line2',
   *   'postal_code',
   *   'sorting_code',
   *   'dependent_locality',
   *   'dependent_locality_code',
   *   'locality',
   *   'locality_code',
   *   'administrative_area',
   *   'administrative_area_code',
   *   'country',
   *   'country_code',
   *
   *   'formatted_address',
   * ]
   * @endcode
   *
   * @param array $atomics
   *   Address components.
   *
   * @return array||null
   *   Address or NULL.
   */
  public function format(array $atomics);

}
