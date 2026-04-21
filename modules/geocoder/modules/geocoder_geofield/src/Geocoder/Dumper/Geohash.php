<?php

namespace Drupal\geocoder_geofield\Geocoder\Dumper;

use Geocoder\Dumper\Dumper;
use Geocoder\Location;

/**
 * Class for Geohash.
 */
class Geohash extends Geometry implements Dumper {

  /**
   * Dumper.
   *
   * @var \Geocoder\Dumper\Dumper
   */
  protected $dumper;

  /**
   * Geophp interface.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  protected $geophp;

  /**
   * {@inheritdoc}
   */
  public function dump(Location $location) {
    return parent::dump($location)->out('geohash');
  }

}
