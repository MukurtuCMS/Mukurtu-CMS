<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides Spiderfying function.
 *
 * @MapFeature(
 *   id = "spiderfying",
 *   name = @Translation("Spiderfying"),
 *   description = @Translation("Split up overlapping markers on click."),
 *   type = "google_maps",
 * )
 */
class Spiderfying extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'spiderfiable_marker_path' => base_path() . \Drupal::service('extension.list.module')->getPath('geolocation_google_maps') . '/images/marker-plus.svg',
      'markersWontMove' => TRUE,
      'markersWontHide' => FALSE,
      'keepSpiderfied' => TRUE,
      'ignoreMapClick' => FALSE,
      'nearbyDistance' => 20,
      'circleSpiralSwitchover' => 9,
      'circleFootSeparation' => 23,
      'spiralFootSeparation' => 26,
      'spiralLengthStart' => 11,
      'spiralLengthFactor' => 4,
      'legWeight' => 1.5,
      'spiralIconWidth' => 23,
      'spiralIconHeight' => 32,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form['spiderfiable_marker_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Marker Path'),
      '#description' => $this->t('Set relative or absolute path to the image to be displayed while markers are spiderfiable. Tokens supported.'),
      '#default_value' => $settings['spiderfiable_marker_path'],
    ];

    $form['markersWontMove'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Markers won't move"),
      '#description' => $this->t('If you know that you won’t be moving any of the markers you add to this instance, you can save memory by setting this to true.'),
      '#default_value' => $settings['markersWontMove'],
    ];

    $form['markersWontHide'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Markers won't hide"),
      '#description' => $this->t('If you know that you won’t be hiding any of the markers you add to this instance, you can save memory by setting this to true.'),
      '#default_value' => $settings['markersWontHide'],
    ];

    $form['keepSpiderfied'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Keep spiderfied'),
      '#description' => $this->t('By default, the OverlappingMarkerSpiderfier works like Google Earth, in that when you click a spiderfied marker, the markers unspiderfy before any other action takes place. Setting this to true overrides this behavior.'),
      '#default_value' => $settings['keepSpiderfied'],
    ];

    $form['ignoreMapClick'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ignore map click'),
      '#description' => $this->t('By default, clicking an empty spot on the map causes spiderfied markers to unspiderfy. Setting this option to true suppresses that behavior.'),
      '#default_value' => $settings['ignoreMapClick'],
    ];

    $form['nearbyDistance'] = [
      '#type' => 'number',
      '#title' => $this->t('Nearby distance'),
      '#description' => $this->t('This is the pixel radius within which a marker is considered to be overlapping a clicked marker.'),
      '#default_value' => $settings['nearbyDistance'],
    ];

    $form['circleSpiralSwitchover'] = [
      '#type' => 'number',
      '#title' => $this->t('Circle spiral switchover'),
      '#description' => $this->t('This is the lowest number of markers that will be fanned out into a spiral instead of a circle.'),
      '#default_value' => $settings['circleSpiralSwitchover'],
    ];

    $form['circleFootSeparation'] = [
      '#type' => 'number',
      '#title' => $this->t('Circle foot separation'),
      '#description' => $this->t('Determines the positioning of markers when spiderfied out into a circle.'),
      '#default_value' => $settings['circleFootSeparation'],
    ];

    $form['spiralFootSeparation'] = [
      '#type' => 'number',
      '#title' => $this->t('Spiral Foot Separation'),
      '#description' => $this->t('Determines the positioning of markers when spiderfied out into a spiral.'),
      '#default_value' => $settings['spiralFootSeparation'],
    ];

    $form['spiralLengthStart'] = [
      '#type' => 'number',
      '#title' => $this->t('Spiral length start'),
      '#default_value' => $settings['spiralLengthStart'],
    ];

    $form['spiralLengthFactor'] = [
      '#type' => 'number',
      '#title' => $this->t('Spiral length factor'),
      '#default_value' => $settings['spiralLengthFactor'],
    ];

    $form['legWeight'] = [
      '#type' => 'number',
      '#step' => '.1',
      '#title' => $this->t('Leg weight'),
      '#description' => $this->t('This determines the thickness of the lines joining spiderfied markers to their original locations.'),
      '#default_value' => $settings['legWeight'],
    ];

    $form['spiralIconWidth'] = [
      '#type' => 'number',
      '#title' => $this->t('Spiral Icon width'),
      '#description' => $this->t('Determines the width in Pixel of the marker'),
      '#default_value' => $settings['spiralIconWidth'],
    ];

    $form['spiralIconHeight'] = [
      '#type' => 'number',
      '#title' => $this->t('Spiral Icon height'),
      '#description' => $this->t('Determines the height in Pixel of the marker'),
      '#default_value' => $settings['spiralIconHeight'],
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
                  'spiderfiable_marker_path' => $feature_settings['spiderfiable_marker_path'],
                  'markersWontMove' => $feature_settings['markersWontMove'],
                  'markersWontHide' => $feature_settings['markersWontHide'],
                  'keepSpiderfied' => $feature_settings['keepSpiderfied'],
                  'ignoreMapClick' => $feature_settings['ignoreMapClick'],
                  'nearbyDistance' => $feature_settings['nearbyDistance'],
                  'circleSpiralSwitchover' => $feature_settings['circleSpiralSwitchover'],
                  'circleFootSeparation' => $feature_settings['circleFootSeparation'],
                  'spiralFootSeparation' => $feature_settings['spiralFootSeparation'],
                  'spiralLengthStart' => $feature_settings['spiralLengthStart'],
                  'spiralLengthFactor' => $feature_settings['spiralLengthFactor'],
                  'legWeight' => $feature_settings['legWeight'],
                  'spiralIconWidth' => $feature_settings['spiralIconWidth'],
                  'spiralIconHeight' => $feature_settings['spiralIconHeight'],
                ],
              ],
            ],
          ],
        ],
      ]
    );

    if (!empty($feature_settings['spiderfiable_marker_path'])) {
      $path = \Drupal::token()->replace($feature_settings['spiderfiable_marker_path'], $context);
      $render_array['#attached']['drupalSettings']['geolocation']['maps'][$render_array['#id']]['spiderfying']['spiderfiable_marker_path'] = $path;
    }

    return $render_array;
  }

}
