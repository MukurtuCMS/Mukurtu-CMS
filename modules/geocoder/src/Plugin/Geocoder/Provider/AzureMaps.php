<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a AzureMaps geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "azuremaps",
 *   name = "AzureMaps",
 *   handler = "\Geocoder\Provider\AzureMaps\AzureMaps",
 *   arguments = {
 *     "subscriptionKey" = ""
 *   }
 * )
 */
class AzureMaps extends ConfigurableProviderUsingHandlerWithAdapterBase {}
