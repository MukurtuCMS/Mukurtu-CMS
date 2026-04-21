<?php

namespace Drupal\geocoder\Plugin\Geocoder\Dumper;

use Drupal\geocoder\DumperBase;

/**
 * Provides a Wkb geocoder dumper plugin.
 *
 * @GeocoderDumper(
 *   id = "wkb",
 *   name = "WKB",
 *   handler = "\Geocoder\Dumper\Wkb"
 * )
 */
class Wkb extends DumperBase {}
