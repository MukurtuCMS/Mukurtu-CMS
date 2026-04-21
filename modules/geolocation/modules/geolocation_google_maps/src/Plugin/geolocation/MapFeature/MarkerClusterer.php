<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;
use Drupal\geolocation_google_maps\Plugin\geolocation\MapProvider\GoogleMaps;

/**
 * Provides marker clusterer.
 *
 * @MapFeature(
 *   id = "marker_clusterer",
 *   name = @Translation("Marker Clusterer"),
 *   description = @Translation("Group elements on the map."),
 *   type = "google_maps",
 * )
 */
class MarkerClusterer extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'image_path' => '',
      'styles' => '',
      'max_zoom' => 15,
      'zoom_on_click' => TRUE,
      'average_center' => FALSE,
      'grid_size' => 60,
      'minimum_cluster_size' => 2,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->t('Various <a href=":url">examples</a> are available.', [':url' => 'https://developers.google.com/maps/documentation/javascript/marker-clustering']),
    ];
    $form['image_path'] = [
      '#title' => $this->t('Cluster image path'),
      '#type' => 'textfield',
      '#default_value' => $settings['image_path'],
      '#description' => $this->t("Set the marker image path. If omitted, the default image path %url will be used.", ['%url' => 'https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/m']),
    ];
    $form['styles'] = [
      '#title' => $this->t('Styles of the Cluster'),
      '#type' => 'textarea',
      '#default_value' => $settings['styles'],
      '#description' => $this->t(
        'Set custom Cluster styles in JSON Format. Custom Styles have to be set for all 5 Cluster Images. See the <a href=":reference">reference</a> for details.',
        [':reference' => 'https://googlemaps.github.io/v3-utility-library/interfaces/_google_markerclustererplus.clustericonstyle.html']
      ),
    ];
    $form['zoom_on_click'] = [
      '#title' => $this->t('Zoom on click'),
      '#type' => 'checkbox',
      '#description' => $this->t('Whether clicking zooms in on a cluster.'),
      '#default_value' => $settings['zoom_on_click'],
    ];
    $form['average_center'] = [
      '#title' => $this->t('Average center'),
      '#type' => 'checkbox',
      '#description' => $this->t('Whether the center of each cluster should be the average of all markers in the cluster.'),
      '#default_value' => $settings['average_center'],
    ];
    $form['grid_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Grid size'),
      '#description' => $this->t('Set the grid size for clustering.'),
      '#size' => 4,
      '#default_value' => $settings['grid_size'],
    ];
    $form['minimum_cluster_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Minimum cluster size'),
      '#description' => $this->t('Set the minimum size for a cluster of markers.'),
      '#size' => 4,
      '#default_value' => $settings['minimum_cluster_size'],
    ];
    $form['max_zoom'] = [
      '#title' => $this->t('Max Zoom'),
      '#type' => 'number',
      '#min' => GoogleMaps::$minZoomLevel,
      '#max' => GoogleMaps::$maxZoomLevel,
      '#default_value' => $settings['max_zoom'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSettingsForm(array $values, FormStateInterface $form_state, array $parents) {
    $marker_clusterer_styles = $values['styles'];
    if (!empty($marker_clusterer_styles)) {
      $style_parents = $parents;
      $style_parents[] = 'styles';
      if (!is_string($marker_clusterer_styles)) {
        $form_state->setErrorByName(implode('][', $style_parents), $this->t('Please enter a JSON string as style.'));
      }
      $json_result = json_decode($marker_clusterer_styles);
      if ($json_result === NULL) {
        $form_state->setErrorByName(implode('][', $style_parents), $this->t('Decoding style JSON failed. Error: %error.', ['%error' => json_last_error()]));
      }
      elseif (!is_array($json_result)) {
        $form_state->setErrorByName(implode('][', $style_parents), $this->t('Decoded style JSON is not an array.'));
      }
    }

    if (!is_numeric($values['grid_size'])) {
      $form_state->setErrorByName(implode('][', $parents) . '][grid_size', $this->t('Grid size must be a number.'));
    }

    if (!is_numeric($values['minimum_cluster_size'])) {
      $form_state->setErrorByName(implode('][', $parents) . '][minimum_cluster_size', $this->t('Minimum cluster size must be a number.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings, array $context = []) {
    $render_array = parent::alterMap($render_array, $feature_settings, $context);

    if (
      !empty($feature_settings['styles'])
      && is_string($feature_settings['styles'])
    ) {
      $feature_settings['styles'] = json_decode($feature_settings['styles']);
    }

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'library' => [
          'geolocation_google_maps/mapfeature.' . $this->getPluginId(),
        ],
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                $this->getPluginId() => [
                  'enable' => TRUE,
                  'imagePath' => $feature_settings['image_path'],
                  'styles' => $feature_settings['styles'],
                  'maxZoom' => (int) $feature_settings['max_zoom'],
                  'gridSize' => (int) $feature_settings['grid_size'],
                  'zoomOnClick' => (boolean) $feature_settings['zoom_on_click'],
                  'averageCenter' => (int) $feature_settings['average_center'],
                  'minimumClusterSize' => (int) $feature_settings['minimum_cluster_size'],
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
