<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a GoogleMaps geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "googlemaps",
 *   name = "GoogleMaps",
 *   handler = "\Geocoder\Provider\GoogleMaps\GoogleMaps",
 *   arguments = {
 *     "region" = "",
 *     "apiKey" = ""
 *   }
 * )
 */
class GoogleMaps extends ConfigurableProviderUsingHandlerWithAdapterBase {}
