<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides an Algolia Places geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "locationiq",
 *   name = "LocationIQ",
 *   handler = "\Geocoder\Provider\LocationIQ\LocationIQ",
 *   arguments = {
 *     "apiKey" = ""
 *   }
 * )
 */
class LocationIQ extends ConfigurableProviderUsingHandlerWithAdapterBase {}
