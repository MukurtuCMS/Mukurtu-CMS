<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a BANFrance geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "banfrance",
 *   name = "BANFrance",
 *   handler = "\Geocoder\Provider\BANFrance\BANFrance",
 *   arguments = {},
 *   throttle = {
 *     "period" = 1,
 *     "limit" = 50
 *   }
 * )
 */
class BANFrance extends ConfigurableProviderUsingHandlerWithAdapterBase {}
