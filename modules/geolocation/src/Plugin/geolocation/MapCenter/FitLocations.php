<?php

namespace Drupal\geolocation\Plugin\geolocation\MapCenter;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapCenterBase;
use Drupal\geolocation\MapCenterInterface;

/**
 * Fixed coordinates map center.
 *
 * ID for compatibility with v1.
 *
 * @MapCenter(
 *   id = "fit_bounds",
 *   name = @Translation("Fit locations"),
 *   description = @Translation("Automatically fit map to displayed locations."),
 * )
 */
class FitLocations extends MapCenterBase implements MapCenterInterface {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'min_zoom' => FALSE,
      'reset_zoom' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm($option_id = NULL, array $settings = [], $context = NULL) {
    $form = parent::getSettingsForm($option_id, $settings, $context);
    $form['min_zoom'] = [
      '#type' => 'number',
      '#min' => 0,
      '#step' => 1,
      '#title' => $this->t('Set a minimum zoom, especially useful when only location is centered on.'),
      '#default_value' => $settings['min_zoom'],
    ];
    $form['reset_zoom'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reset zoom after fit.'),
      '#default_value' => $settings['reset_zoom'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $map, $center_option_id, array $center_option_settings, $context = NULL) {
    $map = parent::alterMap($map, $center_option_id, $center_option_settings, $context);
    $map['#attached'] = BubbleableMetadata::mergeAttachments($map['#attached'], [
      'library' => [
        'geolocation/map_center.fitlocations',
      ],
    ]);

    return $map;
  }

}
