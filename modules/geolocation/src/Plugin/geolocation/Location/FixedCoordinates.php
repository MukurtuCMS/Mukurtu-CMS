<?php

namespace Drupal\geolocation\Plugin\geolocation\Location;

use Drupal\geolocation\LocationBase;
use Drupal\geolocation\LocationInterface;

/**
 * Fixed coordinates map center.
 *
 * PluginID for compatibility with v1.
 *
 * @Location(
 *   id = "fixed_value",
 *   name = @Translation("Fixed coordinates"),
 *   description = @Translation("Use preset fixed values as center."),
 * )
 */
class FixedCoordinates extends LocationBase implements LocationInterface {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'latitude' => '',
      'longitude' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm($option_id = NULL, array $settings = [], $context = NULL) {
    $settings = $this->getSettings($settings);

    $form = [
      'latitude' => [
        '#type' => 'textfield',
        '#title' => $this->t('Latitude'),
        '#default_value' => $settings['latitude'],
        '#size' => 60,
        '#maxlength' => 128,
      ],
      'longitude' => [
        '#type' => 'textfield',
        '#title' => $this->t('Longitude'),
        '#default_value' => $settings['longitude'],
        '#size' => 60,
        '#maxlength' => 128,
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getCoordinates($center_option_id, array $center_option_settings, $context = NULL) {
    $settings = $this->getSettings($center_option_settings);

    return [
      'lat' => (float) $settings['latitude'],
      'lng' => (float) $settings['longitude'],
    ];
  }

}
