<?php

namespace Drupal\geocoder_test_provider\Plugin\Geocoder\Provider;

use Drupal\geocoder\ProviderUsingHandlerBase;

/**
 * Provides a Mock geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "geocoder_test_provider",
 *   name = "Geocoder Test Provider",
 *   handler = "\Drupal\geocoder_test_provider\Geocoder\Provider\MockProvider",
 *   arguments = { }
 * )
 */
class MockProvider extends ProviderUsingHandlerBase {}
