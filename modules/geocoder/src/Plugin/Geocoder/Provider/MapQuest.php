<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a MapQuest geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "mapquest",
 *   name = "MapQuest",
 *   handler = "\Geocoder\Provider\MapQuest\MapQuest",
 *   arguments = {
 *     "apiKey" = "",
 *     "licensed" = FALSE
 *   }
 * )
 */
class MapQuest extends ConfigurableProviderUsingHandlerWithAdapterBase {}
