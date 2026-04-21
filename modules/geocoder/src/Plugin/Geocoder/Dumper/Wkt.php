<?php

namespace Drupal\geocoder\Plugin\Geocoder\Dumper;

use Drupal\geocoder\DumperBase;

/**
 * Provides a WKT geocoder dumper plugin.
 *
 * @GeocoderDumper(
 *   id = "wkt",
 *   name = "WKT",
 *   handler = "\Geocoder\Dumper\Wkt"
 * )
 */
class Wkt extends DumperBase {}
