<?php

namespace Drupal\geocoder\Plugin\Geocoder\Dumper;

use Drupal\geocoder\DumperBase;

/**
 * Provides a KML geocoder dumper plugin.
 *
 * @GeocoderDumper(
 *   id = "kml",
 *   name = "KML",
 *   handler = "\Geocoder\Dumper\Kml"
 * )
 */
class Kml extends DumperBase {}
