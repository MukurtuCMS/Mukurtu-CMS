<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides Leaflet.
 *
 * @MapFeature(
 *   id = "leaflet_max_bounds",
 *   name = @Translation("Max Bounds"),
 *   description = @Translation("Restrict map to set bounds."),
 *   type = "leaflet",
 * )
 */
class LeafletMaxBounds extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'north' => '',
      'south' => '',
      'east' => '',
      'west' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form['north'] = [
      '#type' => 'textfield',
      '#title' => $this->t('North'),
      '#size' => 15,
      '#default_value' => $settings['north'],
    ];
    $form['south'] = [
      '#type' => 'textfield',
      '#title' => $this->t('South'),
      '#size' => 15,
      '#default_value' => $settings['south'],
    ];
    $form['east'] = [
      '#type' => 'textfield',
      '#title' => $this->t('East'),
      '#size' => 15,
      '#default_value' => $settings['east'],
    ];
    $form['west'] = [
      '#type' => 'textfield',
      '#title' => $this->t('West'),
      '#size' => 15,
      '#default_value' => $settings['west'],
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
          'geolocation_leaflet/mapfeature.' . $this->getPluginId(),
        ],
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                $this->getPluginId() => [
                  'enable' => TRUE,
                  'north' => $feature_settings['north'],
                  'south' => $feature_settings['south'],
                  'east' => $feature_settings['east'],
                  'west' => $feature_settings['west'],
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
