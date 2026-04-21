<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides an OpenCage geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "opencage",
 *   name = "OpenCage",
 *   handler = "Geocoder\Provider\OpenCage\OpenCage",
 *   arguments = {
 *     "apiKey" = "",
 *   }
 * )
 */
class OpenCage extends ConfigurableProviderUsingHandlerWithAdapterBase {}
