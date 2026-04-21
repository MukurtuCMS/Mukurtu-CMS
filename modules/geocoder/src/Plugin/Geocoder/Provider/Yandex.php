<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * Provides a Yandex geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "yandex",
 *   name = "Yandex",
 *   handler = "\Geocoder\Provider\Yandex\Yandex",
 *   arguments = {
 *     "toponym" = "",
 *     "apiKey" = ""
 *   }
 * )
 */
class Yandex extends ConfigurableProviderUsingHandlerWithAdapterBase {}
