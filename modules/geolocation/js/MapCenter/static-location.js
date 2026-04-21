/**
 * @file
 * Static location center.
 */

(function (Drupal) {
  "use strict";

  Drupal.geolocation = Drupal.geolocation || {};
  Drupal.geolocation.mapCenter = Drupal.geolocation.mapCenter || {};

  /**
   * @param {GeolocationMapInterface} map
   * @param {GeolocationCenterOption} centerOption
   * @param {Boolean} centerOption.success
   */
  Drupal.geolocation.mapCenter.location_plugins = function (map, centerOption) {
    if (typeof centerOption.success !== "undefined" && centerOption.success) {
      return true;
    }
    return false;
  };
})(Drupal);
