<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides Google Maps.
 *
 * @MapFeature(
 *   id = "map_restriction",
 *   name = @Translation("Map Restriction"),
 *   description = @Translation("Restrict map to set bounds."),
 *   type = "google_maps",
 * )
 */
class MapRestriction extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'north' => '',
      'south' => '',
      'east' => '',
      'west' => '',
      'strict' => TRUE,
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
    $form['strict'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('strictBounds'),
      '#default_value' => $settings['strict'],
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
                'map_restriction' => [
                  'enable' => TRUE,
                  'north' => $feature_settings['north'],
                  'south' => $feature_settings['south'],
                  'east' => $feature_settings['east'],
                  'west' => $feature_settings['west'],
                  'strict' => $feature_settings['strict'],
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
