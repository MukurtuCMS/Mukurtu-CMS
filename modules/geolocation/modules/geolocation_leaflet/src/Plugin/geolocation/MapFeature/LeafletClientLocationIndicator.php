<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

use Drupal\geolocation\MapFeatureFrontendBase;

/**
 * Provides continious client location indicator.
 *
 * @MapFeature(
 *   id = "leaflet_client_location_indicator",
 *   name = @Translation("Client Location Indicator"),
 *   description = @Translation("Continuously show client location marker on map."),
 *   type = "leaflet",
 * )
 */
class LeafletClientLocationIndicator extends MapFeatureFrontendBase {

}
