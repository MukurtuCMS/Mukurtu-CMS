<?php

namespace Drupal\geolocation\Plugin\Geocoder\Dumper;

use Drupal\geocoder\DumperBase;
use Geocoder\Location;

/**
 * Provides a geolocation geocoder dumper plugin.
 *
 * @GeocoderDumper(
 *   id = "geolocation_geocoder_v3",
 *   name = "Geolocation Geocoder V3"
 * )
 */
class GeolocationGeocoderV3 extends DumperBase {

  /**
   * {@inheritdoc}
   */
  public function dump(Location $address) {
    $data = $address->toArray();
    $lat = $data['latitude'];
    $lng = $data['longitude'];

    unset($data['latitude'], $data['longitude'], $data['bounds']);

    return [
      'lat' => $lat,
      'lng' => $lng,
      'lat_sin' => sin(deg2rad($lat)),
      'lat_cos' => cos(deg2rad($lat)),
      'lng_rad' => deg2rad($lng),
      'data' => $data,
    ];
  }

}
