<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a FreeGeoIp geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "freegeoip",
 *   name = "FreeGeoIp",
 *   handler = "\Geocoder\Provider\FreeGeoIp\FreeGeoIp",
 *   arguments = {
 *     "baseUrl" = "https://freegeoip.net/json/%s"
 *   }
 * )
 */
class FreeGeoIp extends ConfigurableProviderUsingHandlerWithAdapterBase {}
