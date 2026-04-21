<?php

declare(strict_types=1);

namespace Drupal\geocoder_geofield\Geocoder\Provider;

/**
 * Defines the GeometryProvider interface.
 */
interface GeometryProviderInterface {

  /**
   * Geocode a source string.
   *
   * @param string $filename
   *   The file path with data to be geocoded.
   *
   * @return \Geometry
   *   The Geometry result.
   *
   * @throws \Geocoder\Exception\Exception
   */
  public function geocode($filename): \Geometry;

  /**
   * Reverse ReverseGeocode.
   *
   * @param float $latitude
   *   The latitude.
   * @param float $longitude
   *   The longitude.
   *
   * @return \Geocoder\Model\AddressCollection|null
   *   The AddressCollection object, NULL otherwise.
   *
   * @throws \Geocoder\Exception\Exception
   */
  public function reverse($latitude, $longitude);

  /**
   * Returns the Geometry provider's name.
   *
   * @return string
   *   The GeometryProvider name.
   */
  public function getName(): string;

}
