<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Provides Zoom control element.
 *
 * @MapFeature(
 *   id = "control_zoom",
 *   name = @Translation("Map Control - Zoom"),
 *   description = @Translation("Add button to toggle map type."),
 *   type = "google_maps",
 * )
 */
class ControlGoogleZoom extends ControlGoogleElementBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    $settings = parent::getDefaultSettings();
    $settings['style'] = 'LARGE';

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

    $form['style'] = [
      '#type' => 'select',
      '#title' => $this->t('Style'),
      '#options' => [
        'SMALL' => $this->t('Small'),
        'LARGE' => $this->t('Large'),
      ],
      '#default_value' => $settings['style'],
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
                  'style' => $feature_settings['style'],
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
