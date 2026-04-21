<?php

declare(strict_types=1);

namespace Drupal\geocoder_geofield\Geocoder\Dumper;

use Geocoder\Dumper\Dumper;
use Geocoder\Location;

/**
 * Dumper.
 */
class Geometry implements Dumper {

  /**
   * Dumper.
   *
   * @var \Geocoder\Dumper\Dumper
   */
  private $dumper;

  /**
   * Geophp interface.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  private $geophp;

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $this->dumper = \Drupal::service('plugin.manager.geocoder.dumper')->createInstance('geojson');
    $this->geophp = \Drupal::service('geofield.geophp');
  }

  /**
   * {@inheritdoc}
   */
  public function dump(Location $location) {
    return $this->geophp->load($this->dumper->dump($location), 'json');
  }

}
