<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\geolocation\MapFeatureFrontendBase;

/**
 * Provides continious client location indicator.
 *
 * @MapFeature(
 *   id = "client_location_indicator",
 *   name = @Translation("Client Location Indicator"),
 *   description = @Translation("Continuously show client location marker on map."),
 *   type = "google_maps",
 * )
 */
class ClientLocationIndicator extends MapFeatureFrontendBase {

}
