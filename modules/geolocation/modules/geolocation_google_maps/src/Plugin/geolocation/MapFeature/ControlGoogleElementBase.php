<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Class ControlMapFeatureBase.
 */
abstract class ControlGoogleElementBase extends ControlElementBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    $settings = parent::getDefaultSettings();
    $settings['position'] = 'RIGHT_CENTER';
    $settings['behavior'] = 'default';

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

    $form['behavior'] = [
      '#type' => 'select',
      '#title' => $this->t('Display behavior'),
      '#options' => [
        'default' => $this->t('Default (Display control at 200px width minimum.)'),
        'always' => $this->t('Always (Display control regardless of map dimensions.)'),
      ],
      '#default_value' => $settings['behavior'],
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
                  'position' => $feature_settings['position'],
                  'behavior' => $feature_settings['behavior'],
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
