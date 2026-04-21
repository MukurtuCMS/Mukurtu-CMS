<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ProviderUsingHandlerWithAdapterBase;

/**
 * Provides a HostIp geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "hostip",
 *   name = "HostIp",
 *   handler = "\Geocoder\Provider\HostIp\HostIp",
 * )
 */
class HostIp extends ProviderUsingHandlerWithAdapterBase {}
