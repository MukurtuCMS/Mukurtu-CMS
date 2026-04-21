<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\geolocation\MapFeatureFrontendBase;

/**
 * Provides map tilt.
 *
 * @MapFeature(
 *   id = "map_disable_tilt",
 *   name = @Translation("Disable Map Tilt"),
 *   description = @Translation("Disable 45° tilted perspective view available for certain locations."),
 *   type = "google_maps",
 * )
 */
class MapTilt extends MapFeatureFrontendBase {

}
