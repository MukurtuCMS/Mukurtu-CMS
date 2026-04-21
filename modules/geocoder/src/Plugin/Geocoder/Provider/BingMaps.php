<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a BingMaps geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "bingmaps",
 *   name = "BingMaps",
 *   handler = "\Geocoder\Provider\BingMaps\BingMaps",
 *   arguments = {
 *     "apiKey" = ""
 *   }
 * )
 */
class BingMaps extends ConfigurableProviderUsingHandlerWithAdapterBase {}
