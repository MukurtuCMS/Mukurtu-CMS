<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

/**
 * Class ControlMapFeatureBase.
 */
abstract class ControlCustomElementBase extends ControlElementBase {

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings, array $context = []) {
    $render_array = parent::alterMap($render_array, $feature_settings, $context);

    $render_array['#controls'][$this->pluginId] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'geolocation-map-control',
          $this->pluginId,
        ],
        'data-google-map-control-position' => $feature_settings['position'],
      ],
    ];

    return $render_array;
  }

}
