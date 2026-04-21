<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\geolocation\MapFeatureFrontendBase;

/**
 * Provides traffic layer.
 *
 * @MapFeature(
 *   id = "google_maps_layer_traffic",
 *   name = @Translation("Traffic layer"),
 *   description = @Translation("Allows you to add real-time traffic information (where supported) to your maps."),
 *   type = "google_maps",
 * )
 */
class LayerTraffic extends MapFeatureFrontendBase {

}
