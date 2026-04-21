<?php

namespace Drupal\geolocation\Plugin\geolocation\MapFeature;

use Drupal\geolocation\MapFeatureBase;

/**
 * Redraw locations as shapes.
 *
 * @MapFeature(
 *   id = "geolocation_shapes",
 *   name = @Translation("Draw Shapes"),
 *   description = @Translation("Draw shapes based on locations."),
 *   type = "all",
 * )
 */
class GeolocationShapes extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'remove_markers' => FALSE,
      'polyline' => TRUE,
      'polyline_title' => '',
      'strokeColor' => '#FF0000',
      'strokeOpacity' => 0.8,
      'strokeWidth' => 2,
      'polygon' => FALSE,
      'polygon_title' => '',
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

    $form['remove_markers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove markers'),
      '#description' => $this->t('Remove location elements and markers from output.'),
      '#default_value' => $settings['remove_markers'],
    ];

    $form['polyline'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Draw polyline'),
      '#description' => $this->t('A polyline is a linear overlay of connected line segments on the map.'),
      '#default_value' => $settings['polyline'],
    ];
    $form['polyline_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Polyline title (Tokens supported)'),
      '#default_value' => $settings['polyline_title'],
      '#states' => [
        'visible' => [
          ['input[name="' . $states_prefix . '[polyline]"]' => ['checked' => TRUE]],
        ],
      ],
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
    $form['strokeWidth'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Stroke width'),
      '#description' => $this->t('The stroke width in pixels.'),
      '#size' => 2,
      '#default_value' => $settings['strokeWidth'],
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
    $form['polygon_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Polygon title (Tokens supported)'),
      '#default_value' => $settings['polygon_title'],
      '#states' => [
        'visible' => [
          ['input[name="' . $states_prefix . '[polygon]"]' => ['checked' => TRUE]],
        ],
      ],
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

    if (empty($render_array['#children']['locations'])) {
      return $render_array;
    }

    $coordinates = [];
    foreach ($render_array['#children']['locations'] as $location) {
      if (empty($location['#coordinates'])) {
        continue;
      }
      $coordinates[] = $location['#coordinates'];
    }

    if ($feature_settings['remove_markers']) {
      unset($render_array['#children']['locations']);
    }

    if ($feature_settings['polyline']) {
      $render_array['#children']['polyline'] = [
        '#type' => 'geolocation_map_polyline',
        '#coordinates' => $coordinates,
        '#title' => \Drupal::token()->replace($feature_settings['polyline_title'], $context),
        '#stroke_color' => $feature_settings['strokeColor'],
        '#stroke_width' => $feature_settings['strokeWidth'],
        '#stroke_opacity' => $feature_settings['strokeOpacity'],
      ];
    }

    if ($feature_settings['polygon']) {
      $render_array['#children']['polygon'] = [
        '#type' => 'geolocation_map_polygon',
        '#coordinates' => $coordinates,
        '#title' => \Drupal::token()->replace($feature_settings['polygon_title'], $context),
        '#stroke_color' => $feature_settings['strokeColor'],
        '#stroke_width' => $feature_settings['strokeWidth'],
        '#stroke_opacity' => $feature_settings['strokeOpacity'],
        '#fill_color' => $feature_settings['fillColor'],
        '#fill_opacity' => $feature_settings['fillOpacity'],
      ];
    }

    return $render_array;
  }

}
