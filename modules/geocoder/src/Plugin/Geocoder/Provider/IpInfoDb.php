<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides an IpInfoDb geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "ipinfodb",
 *   name = "IpInfoDb",
 *   handler = "\Geocoder\Provider\IpInfoDb\IpInfoDb",
 *   arguments = {
 *     "apiKey" = "",
 *     "precision" = "city"
 *   }
 * )
 */
class IpInfoDb extends ConfigurableProviderUsingHandlerWithAdapterBase {}
