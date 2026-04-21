<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ProviderUsingHandlerWithAdapterBase;

/**
 * Provides a Geopunt geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "geopunt",
 *   name = "Geopunt",
 *   handler = "\Geocoder\Provider\Geopunt\Geopunt"
 * )
 */
class Geopunt extends ProviderUsingHandlerWithAdapterBase {}
