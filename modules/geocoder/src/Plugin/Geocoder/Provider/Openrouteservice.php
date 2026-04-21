<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides an Openrouteservice geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "openrouteservice",
 *   name = "Openrouteservice",
 *   handler = "\Geocoder\Provider\OpenRouteService\OpenRouteService",
 *   arguments = {
 *     "apiKey" = ""
 *   }
 * )
 */
class Openrouteservice extends ConfigurableProviderUsingHandlerWithAdapterBase {}
