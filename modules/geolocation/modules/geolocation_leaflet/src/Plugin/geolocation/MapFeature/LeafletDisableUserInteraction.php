<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

use Drupal\geolocation\MapFeatureFrontendBase;

/**
 * Provides disabled interaction.
 *
 * @MapFeature(
 *   id = "leaflet_disable_user_interaction",
 *   name = @Translation("Disable User Interaction"),
 *   description = @Translation("Disable direct user interaction like zooming or panning"),
 *   type = "leaflet",
 * )
 */
class LeafletDisableUserInteraction extends MapFeatureFrontendBase {

}
