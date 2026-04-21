<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a BPost geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "bpost",
 *   name = "bpost",
 *   handler = "\Geocoder\Provider\bpost\bpost",
 *   arguments = {}
 * )
 */
class Bpost extends ConfigurableProviderUsingHandlerWithAdapterBase {}
