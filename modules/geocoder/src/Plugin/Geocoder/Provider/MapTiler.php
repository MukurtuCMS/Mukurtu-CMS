<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a MapTiler geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "maptiler",
 *   name = "MapTiler",
 *   handler = "\Geocoder\Provider\MapTiler\MapTiler",
 *   arguments = {
 *     "key" = "",
 *     "bounds" = "",
 *   }
 * )
 */
class MapTiler extends ConfigurableProviderUsingHandlerWithAdapterBase {}
