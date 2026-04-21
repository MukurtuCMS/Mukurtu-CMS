<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ProviderUsingHandlerWithAdapterBase;

/**
 * Provides a SPW geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "spw",
 *   name = "SPW - Service Public de Wallonie",
 *   handler = "\Geocoder\Provider\SPW\SPW"
 * )
 */
class Spw extends ProviderUsingHandlerWithAdapterBase {}
