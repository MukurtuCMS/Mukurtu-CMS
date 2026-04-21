<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a Addok geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "addok",
 *   name = "Addok",
 *   handler = "\Geocoder\Provider\Addok\Addok",
 *   arguments = {
 *     "rootUrl" = "https://data.geopf.fr/geocodage"
 *   }
 * )
 */
class Addok extends ConfigurableProviderUsingHandlerWithAdapterBase {}
