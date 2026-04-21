<?php

namespace Drupal\geolocation\Plugin\geolocation\Location;

use Drupal\geolocation\LocationBase;
use Drupal\geolocation\LocationInterface;

/**
 * Fixed coordinates map center.
 *
 * @Location(
 *   id = "ipstack",
 *   name = @Translation("ipstack Service"),
 *   description = @Translation("See https://ipstack.com/ website. Limited to 10000 requests per month. Access key required."),
 * )
 */
class IpStack extends LocationBase implements LocationInterface {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'access_key' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm($option_id = NULL, array $settings = [], $context = NULL) {
    $settings = $this->getSettings($settings);

    $form['access_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Key'),
      '#default_value' => $settings['access_key'],
      '#size' => 60,
      '#maxlength' => 128,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getCoordinates($center_option_id, array $center_option_settings, $context = NULL) {
    $settings = $this->getSettings($center_option_settings);
    // Access Key is required.
    if (empty($settings['access_key'])) {
      return [];
    }

    // Get client IP.
    $ip = \Drupal::request()->getClientIp();
    if (empty($ip)) {
      return [];
    }

    // Get data from api.ipstack.com.
    $json = file_get_contents("http://api.ipstack.com/" . $ip . "?access_key=" . $settings['access_key']);
    if (empty($json)) {
      return [];
    }

    $result = json_decode($json, TRUE);
    if (
      empty($result)
      || empty($result['latitude'])
      || empty($result['longitude'])
    ) {
      return [];
    }

    return [
      'lat' => (float) $result['latitude'],
      'lng' => (float) $result['longitude'],
    ];
  }

}
