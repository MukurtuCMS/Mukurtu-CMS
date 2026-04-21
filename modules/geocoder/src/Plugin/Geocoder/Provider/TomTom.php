<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a TomTom geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "tomtom",
 *   name = "TomTom",
 *   handler = "\Geocoder\Provider\TomTom\TomTom",
 *   arguments = {
 *     "apiKey" = ""
 *   }
 * )
 */
class TomTom extends ConfigurableProviderUsingHandlerWithAdapterBase {}
