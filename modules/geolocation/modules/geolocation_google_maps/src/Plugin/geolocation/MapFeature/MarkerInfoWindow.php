<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides marker infowindow.
 *
 * @MapFeature(
 *   id = "marker_infowindow",
 *   name = @Translation("Marker InfoWindow"),
 *   description = @Translation("Open InfoWindow on Marker click."),
 *   type = "google_maps",
 * )
 */
class MarkerInfoWindow extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'info_auto_display' => FALSE,
      'disable_auto_pan' => TRUE,
      'info_window_solitary' => TRUE,
      'max_width' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form['info_window_solitary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Only allow one current open info window.'),
      '#description' => $this->t('If checked, clicking a marker will close the current info window before opening a new one.'),
      '#default_value' => $settings['info_window_solitary'],
    ];

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
                  'infoAutoDisplay' => $feature_settings['info_auto_display'],
                  'disableAutoPan' => $feature_settings['disable_auto_pan'],
                  'infoWindowSolitary' => $feature_settings['info_window_solitary'],
                  'maxWidth' => $feature_settings['max_width'],
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
