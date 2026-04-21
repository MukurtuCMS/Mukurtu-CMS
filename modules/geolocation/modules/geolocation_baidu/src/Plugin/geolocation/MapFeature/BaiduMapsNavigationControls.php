<?php

namespace Drupal\geolocation_baidu\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides map zoom control support.
 *
 * @MapFeature(
 *   id = "baidu_maps_navigation_controls",
 *   name = @Translation("Baidu map controls"),
 *   description = @Translation("Add map zoom controls."),
 *   type = "baidu",
 * )
 */
class BaiduMapsNavigationControls extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    $settings = parent::getDefaultSettings();
    $settings['type'] = 'BMAP_NAVIGATION_CONTROL_LARGE';

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

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => [
        'BMAP_NAVIGATION_CONTROL_LARGE' => $this->t('Standard pan and zoom controls'),
        'BMAP_NAVIGATION_CONTROL_SMALL' => $this->t('Contains pan and zoom buttons'),
        'BMAP_NAVIGATION_CONTROL_PAN' => $this->t('Contains the pan button only'),
        'BMAP_NAVIGATION_CONTROL_ZOOM' => $this->t('Contains zoom buttons only'),
      ],
      '#default_value' => $settings['type'],
    ];

    $form['position'] = [
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#options' => [
        'BMAP_ANCHOR_TOP_LEFT' => $this->t('Top left'),
        'BMAP_ANCHOR_BOTTOM_LEFT' => $this->t('Bottom left'),
        'BMAP_ANCHOR_TOP_RIGHT' => $this->t('Top right'),
        'BMAP_ANCHOR_BOTTOM_RIGHT' => $this->t('Bottom right'),
      ],
      '#default_value' => $settings['position'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings, array $context = []): array {
    $render_array = parent::alterMap($render_array, $feature_settings, $context);

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'library' => [
          'geolocation_baidu/mapfeature.baidu_map_navigation_controls',
        ],
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                $this->getPluginId() => [
                  'enable' => TRUE,
                  'type' => $feature_settings['type'],
                  'position' => $feature_settings['position'],
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
