<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a GraphHopper geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "graphhopper",
 *   name = "GraphHopper",
 *   handler = "\Geocoder\Provider\GraphHopper\GraphHopper",
 *   arguments = {
 *     "apiKey" = ""
 *   }
 * )
 */
class GraphHopper extends ConfigurableProviderUsingHandlerWithAdapterBase {}
