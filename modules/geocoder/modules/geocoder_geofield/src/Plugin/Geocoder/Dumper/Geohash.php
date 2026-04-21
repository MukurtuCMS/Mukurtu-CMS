<?php

namespace Drupal\geocoder_geofield\Plugin\Geocoder\Dumper;

use Drupal\geocoder\DumperBase;
use Geocoder\Location;

/**
 * Provides a geohash geocoder dumper plugin.
 *
 * @GeocoderDumper(
 *   id = "geohash",
 *   name = "Geohash",
 *   handler = "\Drupal\geocoder_geofield\Geocoder\Dumper\Geometry"
 * )
 */
class Geohash extends DumperBase {

  /**
   * {@inheritdoc}
   */
  public function dump(Location $location) {
    return parent::dump($location)->out('geohash');
  }

}
