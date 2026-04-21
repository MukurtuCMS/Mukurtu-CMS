<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides marker clusterer.
 *
 * @MapFeature(
 *   id = "leaflet_marker_clusterer",
 *   name = @Translation("Marker Clusterer"),
 *   description = @Translation("Cluster close markers together."),
 *   type = "leaflet",
 * )
 */
class LeafletMarkerClusterer extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    $default_settings = parent::getDefaultSettings();

    $default_settings['cluster_settings'] = [
      'show_coverage_on_hover' => TRUE,
      'zoom_to_bounds_on_click' => TRUE,
    ];
    $default_settings['disable_clustering_at_zoom'] = 0;
    $default_settings['custom_marker_settings'] = '';

    return $default_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $options = [
      'show_coverage_on_hover' => $this->t('Hovering over a cluster shows the bounds of its markers.'),
      'zoom_to_bounds_on_click' => $this->t('Clicking a cluster zooms to the bounds.'),
    ];

    $form['cluster_settings'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#title' => $this->t('Marker Cluster default settings'),
      '#default_value' => array_keys(array_filter($settings['cluster_settings'])),
    ];

    $form['disable_clustering_at_zoom'] = [
      '#type' => 'number',
      '#min' => 0,
      '#max' => 20,
      '#step' => 1,
      '#size' => 2,
      '#title' => $this->t('Disable clustering at zoom'),
      '#description' => $this->t('If set, at this zoom level and below, markers will not be clustered.'),
      '#default_value' => $settings['disable_clustering_at_zoom'],
    ];

    $form['custom_marker_settings'] = [
      '#type' => 'textarea',
      '#description' => $this->t('Custom marker settings in JSON format like: {"small": {"radius": 40, "limit": 10}, "medium": {"radius": 60, "limit": 50}}.'),
      '#default_value' => $settings['custom_marker_settings'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings, array $context = []) {
    $render_array = parent::alterMap($render_array, $feature_settings, $context);
    $cluster_settings = NULL;
    if (isset($feature_settings['cluster_settings'])) {
      $cluster_settings = $feature_settings['cluster_settings'];
    }
    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'library' => [
          'geolocation_leaflet/mapfeature.' . $this->getPluginId(),
        ],
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                $this->getPluginId() => [
                  'enable' => TRUE,
                  'showCoverageOnHover' => $cluster_settings['show_coverage_on_hover'],
                  'zoomToBoundsOnClick' => $cluster_settings['zoom_to_bounds_on_click'],
                  'disableClusteringAtZoom' => (int) $feature_settings['disable_clustering_at_zoom'],
                  'customMarkerSettings' => Json::decode($feature_settings['custom_marker_settings']),
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
