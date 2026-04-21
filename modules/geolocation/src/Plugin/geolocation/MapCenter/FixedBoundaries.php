<?php

namespace Drupal\geolocation\Plugin\geolocation\MapCenter;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapCenterBase;
use Drupal\geolocation\MapCenterInterface;

/**
 * Fixed boundaries map center.
 *
 * @MapCenter(
 *   id = "fixed_boundaries",
 *   name = @Translation("Fixed boundaries"),
 *   description = @Translation("Fit map to preset boundaries."),
 * )
 */
class FixedBoundaries extends MapCenterBase implements MapCenterInterface {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'north' => NULL,
      'east' => NULL,
      'south' => NULL,
      'west' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm($option_id = NULL, array $settings = [], $context = NULL) {
    $form = parent::getSettingsForm($option_id, $settings, $context);
    $form['north'] = [
      '#type' => 'number',
      '#title' => $this->t('Northern boundary.'),
      '#default_value' => $settings['north'],
      '#min' => -90,
      '#max' => 90,
      '#step' => 0.001,
    ];
    $form['east'] = [
      '#type' => 'number',
      '#title' => $this->t('Eastern boundary.'),
      '#default_value' => $settings['east'],
      '#min' => -180,
      '#max' => 180,
      '#step' => 0.001,
    ];
    $form['south'] = [
      '#type' => 'number',
      '#title' => $this->t('Southern boundary.'),
      '#default_value' => $settings['south'],
      '#min' => -90,
      '#max' => 90,
      '#step' => 0.001,
    ];
    $form['west'] = [
      '#type' => 'number',
      '#title' => $this->t('Western boundary.'),
      '#default_value' => $settings['west'],
      '#min' => -180,
      '#max' => 180,
      '#step' => 0.001,
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
        'geolocation/map_center.fixed_boundaries',
      ],
    ]);

    return $map;
  }

}
