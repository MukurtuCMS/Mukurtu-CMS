<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a Pelias geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "pelias",
 *   name = "Pelias",
 *   handler = "\Geocoder\Provider\Pelias\Pelias",
 *   arguments = {
 *     "root" = "",
 *     "version" = ""
 *   }
 * )
 */
class Pelias extends ConfigurableProviderUsingHandlerWithAdapterBase {}
