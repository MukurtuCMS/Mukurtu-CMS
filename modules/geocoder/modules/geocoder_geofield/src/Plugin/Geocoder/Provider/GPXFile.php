<?php

namespace Drupal\geocoder_geofield\Plugin\Geocoder\Provider;

use Drupal\geocoder\ProviderUsingHandlerBase;

/**
 * Provides a File geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "gpxfile",
 *   name = "GPX File",
 *   handler = "\Drupal\geocoder_geofield\Geocoder\Provider\GPXFile"
 * )
 */
class GPXFile extends ProviderUsingHandlerBase {}
