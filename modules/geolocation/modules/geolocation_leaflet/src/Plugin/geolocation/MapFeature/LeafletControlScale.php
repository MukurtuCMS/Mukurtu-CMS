<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Provides Scale control element.
 *
 * @MapFeature(
 *   id = "leaflet_control_scale",
 *   name = @Translation("Map Control - Scale"),
 *   description = @Translation("A simple scale control that shows the scale of the current center of screen in metric (m/km) and imperial (mi/ft) systems."),
 *   type = "leaflet",
 * )
 */
class LeafletControlScale extends ControlElementBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'metric' => TRUE,
      'imperial' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form = parent::getSettingsForm($settings, $parents);

    $form['metric'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Metric'),
      '#description' => $this->t('Whether to show the metric scale line (m/km).'),
      '#default_value' => $settings['metric'],
    ];
    $form['imperial'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Imperial'),
      '#description' => $this->t('Whether to show the imperial scale line (mi/ft).'),
      '#default_value' => $settings['imperial'],
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
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                $this->getPluginId() => [
                  'metric' => $feature_settings['metric'],
                  'imperial' => $feature_settings['imperial'],
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
