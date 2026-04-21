<?php

namespace Drupal\geolocation;

/**
 * Class KeyProvider plugin.
 *
 * @package Drupal\geolocation
 */
class KeyProvider {

  /**
   * Get actual API key from key module, if possible.
   *
   * So that we don't need to store plain text api key secrets in config file.
   */
  public static function getKeyValue($api_key) {
    if (empty($api_key)) {
      return $api_key;
    }

    // If the "Key" module exists, assume we are storing the key name instead of
    // the actual value, which should be a secret not saved in config.
    if (!\Drupal::moduleHandler()->moduleExists('key')) {
      return $api_key;
    }

    $store = \Drupal::service('key.repository')->getKey($api_key);
    if (empty($store)) {
      return $api_key;
    }

    $store_key = $store->getKeyValue();
    if (empty($store_key)) {
      return $api_key;
    }

    return $store_key;
  }

}
