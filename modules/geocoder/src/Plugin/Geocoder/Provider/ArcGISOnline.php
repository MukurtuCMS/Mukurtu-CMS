<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides an ArcGisOnline geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "arcgisonline",
 *   name = "ArcGisOnline",
 *   handler = "\Geocoder\Provider\ArcGISOnline\ArcGISOnline",
 *   arguments = {
 *     "sourceCountry" = ""
 *   }
 * )
 */
class ArcGISOnline extends ConfigurableProviderUsingHandlerWithAdapterBase {}
