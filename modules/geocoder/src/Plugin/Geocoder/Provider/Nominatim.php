<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a Nominatim geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "nominatim",
 *   name = "Nominatim",
 *   handler = "\Geocoder\Provider\Nominatim\Nominatim",
 *   arguments = {
 *     "rootUrl" = "https://nominatim.openstreetmap.org",
 *     "userAgent" = "",
 *     "referer" = ""
 *   },
 *   throttle = {
 *     "period" = 2,
 *     "limit" = 1
 *   }
 * )
 */
class Nominatim extends ConfigurableProviderUsingHandlerWithAdapterBase {}
