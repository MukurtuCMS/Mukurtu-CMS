<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Provides MapType control element.
 *
 * @MapFeature(
 *   id = "control_maptype",
 *   name = @Translation("Map Control - MapType"),
 *   description = @Translation("Add button to toggle map type."),
 *   type = "google_maps",
 * )
 */
class ControlGoogleMapType extends ControlGoogleElementBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    $settings = parent::getDefaultSettings();
    $settings['style'] = 'DEFAULT';
    $settings['position'] = 'RIGHT_BOTTOM';

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
        'DEFAULT' => $this->t('Default (Map size dependent)'),
        'HORIZONTAL_BAR' => $this->t('Horizontal Bar'),
        'DROPDOWN_MENU' => $this->t('Dropdown Menu'),
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
