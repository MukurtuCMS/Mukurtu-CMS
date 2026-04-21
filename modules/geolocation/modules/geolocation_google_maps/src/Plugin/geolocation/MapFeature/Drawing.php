<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides Drawing function.
 *
 * @MapFeature(
 *   id = "drawing",
 *   name = @Translation("DEPRECATED - Drawing"),
 *   description = @Translation("Draw lines and areas over markers."),
 *   type = "google_maps",
 * )
 */
class Drawing extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'polyline' => FALSE,
      'strokeColor' => '#FF0000',
      'strokeOpacity' => 0.8,
      'strokeWeight' => 2,
      'geodesic' => FALSE,
      'polygon' => FALSE,
      'fillColor' => '#FF0000',
      'fillOpacity' => 0.35,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsSummary(array $settings) {
    $summary = parent::getSettingsSummary($settings);
    $summary[] = $this->t('Draw polyline: @polyline', ['@polyline' => $settings['polyline'] ? $this->t('Yes') : $this->t('No')]);
    $summary[] = $this->t('Draw polygon: @polygon', ['@polygon' => $settings['polygon'] ? $this->t('Yes') : $this->t('No')]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $states_prefix = array_shift($parents) . '[' . implode('][', $parents) . ']';

    $form['polyline'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Draw polyline'),
      '#description' => $this->t('A polyline is a linear overlay of connected line segments on the map.'),
      '#default_value' => $settings['polyline'],
    ];
    $form['strokeColor'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Stroke color'),
      '#description' => $this->t('The stroke color. All CSS3 colors are supported except for extended named colors.'),
      '#size' => 4,
      '#default_value' => $settings['strokeColor'],
      '#states' => [
        'visible' => [
          ['input[name="' . $states_prefix . '[polyline]"]' => ['checked' => TRUE]],
          ['input[name="' . $states_prefix . '[polygon]"]' => ['checked' => TRUE]],
        ],
      ],
    ];
    $form['strokeOpacity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Stroke opacity'),
      '#description' => $this->t('The stroke opacity between 0.0 and 1.0.'),
      '#size' => 2,
      '#default_value' => $settings['strokeOpacity'],
      '#states' => [
        'visible' => [
          ['input[name="' . $states_prefix . '[polyline]"]' => ['checked' => TRUE]],
          ['input[name="' . $states_prefix . '[polygon]"]' => ['checked' => TRUE]],
        ],
      ],
    ];
    $form['strokeWeight'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Stroke weight'),
      '#description' => $this->t('The stroke width in pixels.'),
      '#size' => 2,
      '#default_value' => $settings['strokeWeight'],
      '#states' => [
        'visible' => [
          ['input[name="' . $states_prefix . '[polyline]"]' => ['checked' => TRUE]],
          ['input[name="' . $states_prefix . '[polygon]"]' => ['checked' => TRUE]],
        ],
      ],
    ];
    $form['geodesic'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Geodesic lines'),
      '#description' => $this->t('When true, edges of the polygon are interpreted as geodesic and will follow the curvature of the Earth. When false, edges of the polygon are rendered as straight lines in screen space.'),
      '#default_value' => $settings['geodesic'],
      '#states' => [
        'visible' => [
          ['input[name="' . $states_prefix . '[polyline]"]' => ['checked' => TRUE]],
          ['input[name="' . $states_prefix . '[polygon]"]' => ['checked' => TRUE]],
        ],
      ],
    ];

    $form['polygon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Draw polygon'),
      '#description' => $this->t('Polygons form a closed loop and define a filled region.'),
      '#default_value' => $settings['polygon'],
    ];
    $form['fillColor'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fill color'),
      '#description' => $this->t('The fill color. All CSS3 colors are supported except for extended named colors.'),
      '#size' => 4,
      '#default_value' => $settings['fillColor'],
      '#states' => [
        'visible' => [
          'input[name="' . $states_prefix . '[polygon]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['fillOpacity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fill opacity'),
      '#description' => $this->t('The fill opacity between 0.0 and 1.0.'),
      '#size' => 4,
      '#default_value' => $settings['fillOpacity'],
      '#states' => [
        'visible' => [
          'input[name="' . $states_prefix . '[polygon]"]' => ['checked' => TRUE],
        ],
      ],
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
                  'settings' => $feature_settings,
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
