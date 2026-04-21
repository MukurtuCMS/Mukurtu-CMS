<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Provides Recenter control element.
 *
 * @MapFeature(
 *   id = "control_loading_indicator",
 *   name = @Translation("Map Control - Loading Indicator"),
 *   description = @Translation("When using an interactive map, shows a loading icon and label if there is currently data fetched from the backend via AJAX."),
 *   type = "google_maps",
 * )
 */
class ControlCustomLoadingIndicator extends ControlCustomElementBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    $settings = parent::getDefaultSettings();
    $settings['loading_label'] = 'Loading';

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form = parent::getSettingsForm($settings, $parents);

    $settings = array_replace_recursive(
      self::getDefaultSettings(),
      $settings
    );

    $form['loading_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => $this->t('Shown during loading.'),
      '#default_value' => $settings['loading_label'],
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
                ],
              ],
            ],
          ],
        ],
      ]
    );

    $render_array['#controls'][$this->pluginId]['control_loading_indicator'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $feature_settings['loading_label'],
      '#attributes' => [
        'class' => [
          'loading-indicator',
          'geolocation-context-popup',
          'hidden',
        ],
      ],
    ];

    return $render_array;
  }

}
