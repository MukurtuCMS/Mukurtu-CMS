<?php

namespace Drupal\geolocation_yandex\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides marker balloon.
 *
 * @MapFeature(
 *   id = "yandex_balloon",
 *   name = @Translation("Balloon"),
 *   description = @Translation("Open Balloon on Marker click."),
 *   type = "yandex",
 * )
 */
class YandexBalloon extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'info_auto_display' => FALSE,
      'disable_auto_pan' => TRUE,
      'max_width' => '0',
      'panel_max_map_area' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form['info_auto_display'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically show info text.'),
      '#default_value' => $settings['info_auto_display'],
    ];
    $form['disable_auto_pan'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable automatic panning of map when info bubble is opened.'),
      '#default_value' => $settings['disable_auto_pan'],
    ];
    $form['max_width'] = [
      '#type' => 'number',
      '#min' => 0,
      '#title' => $this->t('Max width in pixel. 0 to ignore.'),
      '#default_value' => $settings['max_width'],
    ];
    $form['panel_max_map_area'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Balloon panel mode.'),
      '#description' => $this->t('The maximum area of the map at which the balloon will be displayed in the panel mode. You can disable panel mode by setting the value to <em>0</em>, and vice versa, you can always show the balloon in panel mode by setting the value to <em>Infinity</em>.'),
      '#default_value' => $settings['panel_max_map_area'],
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
          'geolocation_yandex/mapfeature.' . $this->getPluginId(),
        ],
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                $this->getPluginId() => [
                  'enable' => TRUE,
                  'infoAutoDisplay' => $feature_settings['info_auto_display'],
                  'disableAutoPan' => $feature_settings['disable_auto_pan'],
                  'maxWidth' => $feature_settings['max_width'],
                  'panelMaxMapArea' => $feature_settings['panel_max_map_area'],
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
