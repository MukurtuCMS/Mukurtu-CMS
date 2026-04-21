<?php

namespace Drupal\geocoder\Plugin\Geocoder\Dumper;

use Drupal\geocoder\DumperBase;

/**
 * Provides a GeoJson geocoder dumper plugin.
 *
 * @GeocoderDumper(
 *   id = "geojson",
 *   name = "GeoJson",
 *   handler = "\Geocoder\Dumper\GeoJson"
 * )
 */
class GeoJson extends DumperBase {}
