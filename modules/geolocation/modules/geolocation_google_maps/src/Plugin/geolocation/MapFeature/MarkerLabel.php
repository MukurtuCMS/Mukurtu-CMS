<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides Google Maps.
 *
 * @MapFeature(
 *   id = "marker_label",
 *   name = @Translation("Marker Label Adjustment"),
 *   description = @Translation("Label properties."),
 *   type = "google_maps",
 * )
 */
class MarkerLabel extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'color' => '',
      'font_family' => '',
      'font_size' => '',
      'font_weight' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form['color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Color'),
      '#description' => $this->t('The color of the label text. Default color is black.'),
      '#default_value' => $settings['color'],
    ];

    $form['font_family'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Family'),
      '#description' => $this->t('The font family of the label text (equivalent to the CSS font-family property).'),
      '#default_value' => $settings['font_family'],
    ];

    $form['font_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Size'),
      '#description' => $this->t('The font size of the label text (equivalent to the CSS font-size property). Default size is 14px.'),
      '#default_value' => $settings['font_size'],
    ];

    $form['font_weight'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Weight'),
      '#description' => $this->t('The font weight of the label text (equivalent to the CSS font-weight property).'),
      '#default_value' => $settings['font_weight'],
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
                  'color' => $feature_settings['color'],
                  'fontFamily' => $feature_settings['font_family'],
                  'fontSize' => $feature_settings['font_size'],
                  'fontWeight' => $feature_settings['font_weight'],
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
