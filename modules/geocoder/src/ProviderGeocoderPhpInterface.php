<?php

namespace Drupal\geocoder;

use Geocoder\Collection;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;

/**
 * Interface for geocoder providers wrapping Geocoder PHP.
 *
 * These providers implement geocoding Query objects as defined in
 * \Geocoder\Provider\Provider.
 */
interface ProviderGeocoderPhpInterface {

  /**
   * Geocode a PHP Geocoder query.
   *
   * Works with Geocoder\Provider\Provider based plugins.
   *
   * @param \Geocoder\Query\GeocodeQuery $query
   *   The Geocoder query.
   *
   * @return \Geocoder\Collection
   *   Geocoder result collection.
   *
   * @throws \Geocoder\Exception\Exception
   */
  public function geocodeQuery(GeocodeQuery $query): Collection;

  /**
   * Reverse geocode a PHP Geocoder query.
   *
   * Works with Geocoder\Provider\Provider based plugins.
   *
   * @param \Geocoder\Query\ReverseQuery $query
   *   The Geocoder query.
   *
   * @return \Geocoder\Collection
   *   Geocoder result collection.
   *
   * @throws \Geocoder\Exception\Exception
   */
  public function reverseQuery(ReverseQuery $query): Collection;

}
