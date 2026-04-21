<?php

namespace Drupal\geocoder;

/**
 * Provides an interface for geocoder provider plugins.
 *
 * Providers are plugins that knows how to parse an input, passed as string, and
 * transform it into geographical data.
 */
interface ProviderInterface {

  /**
   * Geocode a source string.
   *
   * @param string $source
   *   The data to be geocoded.
   *
   * @return \Geocoder\Collection|\Geometry|null
   *   The address collection, or the geometry, or NULL.
   */
  public function geocode($source);

  /**
   * Reverse geocode latitude and longitude.
   *
   * @param float $latitude
   *   The latitude.
   * @param float $longitude
   *   The longitude.
   *
   * @return \Geocoder\Collection|null
   *   The AddressCollection object, NULL otherwise.
   */
  public function reverse($latitude, $longitude);

}
