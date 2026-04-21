<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\geolocation\MapFeatureFrontendBase;

/**
 * Provides marker infowindow.
 *
 * @MapFeature(
 *   id = "map_disable_user_interaction",
 *   name = @Translation("Disable User Interaction"),
 *   description = @Translation("Disable any zooming or panning by interaction from the user."),
 *   type = "google_maps",
 * )
 */
class MapDisableUserInteraction extends MapFeatureFrontendBase {

}
