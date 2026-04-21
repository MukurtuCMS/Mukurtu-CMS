<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a MaxMind geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "maxmind",
 *   name = "MaxMind",
 *   handler = "\Geocoder\Provider\MaxMind\MaxMind",
 *   arguments = {
 *     "apiKey" = "",
 *     "service" = "f",
 *   }
 * )
 */
class MaxMind extends ConfigurableProviderUsingHandlerWithAdapterBase {}
