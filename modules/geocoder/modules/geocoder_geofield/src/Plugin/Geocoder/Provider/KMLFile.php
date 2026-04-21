<?php

namespace Drupal\geocoder_geofield\Plugin\Geocoder\Provider;

use Drupal\geocoder\ProviderUsingHandlerBase;

/**
 * Provides a KMLFile geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "kmlfile",
 *   name = "KML File",
 *   handler = "\Drupal\geocoder_geofield\Geocoder\Provider\KMLFile"
 * )
 */
class KMLFile extends ProviderUsingHandlerBase {}
