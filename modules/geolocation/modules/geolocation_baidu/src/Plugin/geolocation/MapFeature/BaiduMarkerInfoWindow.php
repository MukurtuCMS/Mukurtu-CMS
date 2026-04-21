<?php

namespace Drupal\geolocation_baidu\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides marker infowindow.
 *
 * @MapFeature(
 *   id = "baidu_marker_infowindow",
 *   name = @Translation("Marker InfoWindow"),
 *   description = @Translation("Open InfoWindow on Marker click."),
 *   type = "baidu",
 * )
 */
class BaiduMarkerInfoWindow extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings, array $context = []) {
    $render_array = parent::alterMap($render_array, $feature_settings, $context);

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'library' => [
          'geolocation_baidu/mapfeature.' . $this->getPluginId(),
        ],
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                $this->getPluginId() => [
                  'enable' => TRUE,
                ],
              ],
            ],
          ],
        ],
      ]
    );

    return $render_array;
  }

}
