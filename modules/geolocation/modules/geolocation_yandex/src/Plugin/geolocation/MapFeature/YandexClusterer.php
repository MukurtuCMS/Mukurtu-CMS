<?php

namespace Drupal\geolocation_yandex\Plugin\geolocation\MapFeature;

use Drupal\geolocation\MapFeatureFrontendBase;

/**
 * Provides marker clusterer.
 *
 * @MapFeature(
 *   id = "yandex_clusterer",
 *   name = @Translation("Clusterer"),
 *   description = @Translation("Cluster close markers together."),
 *   type = "yandex",
 * )
 */
class YandexClusterer extends MapFeatureFrontendBase {

}
