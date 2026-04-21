<?php

namespace Drupal\geocoder_field\Plugin\Geocoder\Provider;

use Drupal\geocoder\ProviderUsingHandlerBase;

/**
 * Provides a File geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "file",
 *   name = "File",
 *   handler = "\Drupal\geocoder_field\Geocoder\Provider\File",
 * )
 */
class File extends ProviderUsingHandlerBase {}
