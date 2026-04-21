<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\geolocation\MapFeatureFrontendBase;

/**
 * Provides transit layer.
 *
 * @MapFeature(
 *   id = "google_maps_layer_transit",
 *   name = @Translation("Transit layer"),
 *   description = @Translation("Allows you to add real-time transit information (where supported) to your maps."),
 *   type = "google_maps",
 * )
 */
class LayerTransit extends MapFeatureFrontendBase {

}
