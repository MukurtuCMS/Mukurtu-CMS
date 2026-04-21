<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides marker popup.
 *
 * @MapFeature(
 *   id = "leaflet_marker_popup",
 *   name = @Translation("Marker Popup"),
 *   description = @Translation("Open Popup on Marker click."),
 *   type = "leaflet",
 * )
 */
class LeafletMarkerPopup extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'info_auto_display' => FALSE,
      'max_width' => 300,
      'min_width' => 50,
      'max_height' => 0,
      'auto_pan' => TRUE,
      'keep_in_view' => FALSE,
      'close_button' => TRUE,
      'auto_close' => TRUE,
      'close_on_escape_key' => TRUE,
      'class_name' => '',
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

    $form['max_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Max width of the popup, in pixels. 0 will skip setting.'),
      '#default_value' => $settings['max_width'],
    ];

    $form['min_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Min width of the popup, in pixels. 0 will skip setting.'),
      '#default_value' => $settings['min_width'],
    ];

    $form['max_height'] = [
      '#type' => 'number',
      '#title' => $this->t('If set, creates a scrollable container of the given height inside a popup if its content exceeds it. 0 will skip setting.'),
      '#default_value' => $settings['max_height'],
    ];

    $form['auto_pan'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Set it to false if you don't want the map to do panning animation to fit the opened popup."),
      '#default_value' => $settings['auto_pan'],
    ];

    $form['keep_in_view'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set it to true if you want to prevent users from panning the popup off of the screen while it is open.'),
      '#default_value' => $settings['keep_in_view'],
    ];

    $form['close_button'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Controls the presence of a close button in the popup.'),
      '#default_value' => $settings['close_button'],
    ];

    $form['auto_close'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set it to false if you want to override the default behavior of the popup closing when another popup is opened.'),
      '#default_value' => $settings['auto_close'],
    ];

    $form['close_on_escape_key'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set it to false if you want to override the default behavior of the ESC key for closing of the popup.'),
      '#default_value' => $settings['close_on_escape_key'],
    ];

    $form['class_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('A custom CSS class name to assign to the popup.'),
      '#default_value' => $settings['class_name'],
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
          'geolocation_leaflet/mapfeature.' . $this->getPluginId(),
        ],
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                $this->getPluginId() => [
                  'enable' => TRUE,
                  'infoAutoDisplay' => $feature_settings['info_auto_display'],
                  'maxWidth' => $feature_settings['max_width'],
                  'minWidth' => $feature_settings['min_width'],
                  'maxHeight' => $feature_settings['max_height'],
                  'autoPan' => $feature_settings['auto_pan'],
                  'keepInView' => $feature_settings['keep_in_view'],
                  'closeButton' => $feature_settings['close_button'],
                  'autoClose' => $feature_settings['auto_close'],
                  'closeOnEscapeKey' => $feature_settings['close_on_escape_key'],
                  'className' => $feature_settings['class_name'],
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
