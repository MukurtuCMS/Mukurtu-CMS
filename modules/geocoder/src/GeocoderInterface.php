<?php

namespace Drupal\geocoder;

use Geocoder\Model\AddressCollection;
use Geocoder\Query\GeocodeQuery;

/**
 * Provides a geocoder factory method interface.
 */
interface GeocoderInterface {

  /**
   * Geocodes a string.
   *
   * @param \Geocoder\Query\GeocodeQuery|string $address
   *   The GeocodeQuery or string to geocode.
   * @param \Drupal\geocoder\GeocoderProviderInterface[] $providers
   *   A list of Geocoder providers to use to perform the geocoding.
   *
   * @return \Geocoder\Model\AddressCollection|\Geometry|null
   *   An address collection or NULL on geocoding failure.
   */
  public function geocode(GeocodeQuery|string $address, array $providers);

  /**
   * Reverse geocodes coordinates.
   *
   * @param string $latitude
   *   The latitude.
   * @param string $longitude
   *   The longitude.
   * @param \Drupal\geocoder\GeocoderProviderInterface[] $providers
   *   A list of Geocoder providers to use to perform the reverse geocoding.
   *
   * @return \Geocoder\Model\AddressCollection|null
   *   An address collection or NULL on geocoding failure.
   */
  public function reverse(string $latitude, string $longitude, array $providers): ?AddressCollection;

}
