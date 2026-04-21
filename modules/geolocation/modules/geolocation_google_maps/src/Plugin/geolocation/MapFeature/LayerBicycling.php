<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\geolocation\MapFeatureFrontendBase;

/**
 * Provides traffic layer.
 *
 * @MapFeature(
 *   id = "google_maps_layer_bicycling",
 *   name = @Translation("Bicycling layer"),
 *   description = @Translation("Allows you to add real-time bicycling information (where supported) to your maps."),
 *   type = "google_maps",
 * )
 */
class LayerBicycling extends MapFeatureFrontendBase {

}
