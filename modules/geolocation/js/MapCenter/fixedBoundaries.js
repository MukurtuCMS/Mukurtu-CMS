/**
 * @file
 * Fit locations.
 */

(function (Drupal) {
  "use strict";

  Drupal.geolocation = Drupal.geolocation || {};
  Drupal.geolocation.mapCenter = Drupal.geolocation.mapCenter || {};

  /**
   * @param {GeolocationMapInterface} map
   * @param {GeolocationCenterOption} centerOption
   * @param {Number} centerOption.settings.north
   * @param {Number} centerOption.settings.south
   * @param {Number} centerOption.settings.east
   * @param {Number} centerOption.settings.west
   */
  Drupal.geolocation.mapCenter.fixed_boundaries = function (map, centerOption) {
    map.fitBoundaries({
      north: centerOption.settings.north,
      east: centerOption.settings.east,
      south: centerOption.settings.south,
      west: centerOption.settings.west,
    });

    return true;
  };
})(Drupal);
