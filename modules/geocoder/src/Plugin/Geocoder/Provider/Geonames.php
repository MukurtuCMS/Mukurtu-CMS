<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a Geoip geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "geonames",
 *   name = "Geonames",
 *   handler = "\Geocoder\Provider\Geonames\Geonames",
 *   arguments = {
 *     "username" = ""
 *   }
 * )
 */
class Geonames extends ConfigurableProviderUsingHandlerWithAdapterBase {}
