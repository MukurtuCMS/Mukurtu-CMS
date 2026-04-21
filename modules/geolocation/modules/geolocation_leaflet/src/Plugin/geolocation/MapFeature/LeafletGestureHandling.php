<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

use Drupal\geolocation\MapFeatureFrontendBase;

/**
 * Provides gesture handling.
 *
 * @MapFeature(
 *   id = "leaflet_gesture_handling",
 *   name = @Translation("Gesture Handling"),
 *   description = @Translation("Prevents map pan and zoom on page scroll. See <a target='_blank' href='https://github.com/elmarquis/Leaflet.GestureHandling'>https://github.com/elmarquis/Leaflet.GestureHandling</a>"),
 *   type = "leaflet",
 * )
 */
class LeafletGestureHandling extends MapFeatureFrontendBase {

}
