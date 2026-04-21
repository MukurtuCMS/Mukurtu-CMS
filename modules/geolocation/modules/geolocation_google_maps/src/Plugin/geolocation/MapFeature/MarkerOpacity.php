<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides Google Maps.
 *
 * @MapFeature(
 *   id = "marker_opacity",
 *   name = @Translation("Marker Opacity"),
 *   description = @Translation("Opacity properties."),
 *   type = "google_maps",
 * )
 */
class MarkerOpacity extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'opacity' => 1,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form['opacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Opacity'),
      '#step' => 0.01,
      '#min' => 0,
      '#max' => 1,
      '#description' => $this->t('1 = solid, 0 = invisible'),
      '#default_value' => $settings['opacity'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings, array $context = []) {
    $render_array = parent::alterMap($render_array, $feature_settings, $context);

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
                  'opacity' => $feature_settings['opacity'],
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
