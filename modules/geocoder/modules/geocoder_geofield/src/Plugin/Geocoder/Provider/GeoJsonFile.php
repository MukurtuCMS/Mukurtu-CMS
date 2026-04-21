<?php

namespace Drupal\geocoder_geofield\Plugin\Geocoder\Provider;

use Drupal\geocoder\ProviderUsingHandlerBase;

/**
 * Provides a GeoJsonFile geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "geojsonfile",
 *   name = "GeoJson File",
 *   handler = "\Drupal\geocoder_geofield\Geocoder\Provider\GeoJsonFile"
 * )
 */
class GeoJsonFile extends ProviderUsingHandlerBase {}
