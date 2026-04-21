/**
 * @file
 * Views boundary filter center.
 */

(function (Drupal) {
  "use strict";

  Drupal.geolocation = Drupal.geolocation || {};
  Drupal.geolocation.mapCenter = Drupal.geolocation.mapCenter || {};

  /**
   * @param {Object} map
   * @param {Object} centerOption
   * @param {bool} centerOption.clearAddressInput
   * @param {string} centerOption.identifier
   * @param {float} centerOption.latNorthEast
   * @param {float} centerOption.lngNorthEast
   * @param {float} centerOption.latSouthWest
   * @param {float} centerOption.lngSouthWest
   */
  Drupal.geolocation.mapCenter.views_boundary_filter = function (
    map,
    centerOption
  ) {
    var centerBounds = {
      north: centerOption.latNorthEast,
      east: centerOption.lngNorthEast,
      south: centerOption.latSouthWest,
      west: centerOption.lngSouthWest,
    };

    map.fitBoundaries(centerBounds, "views_boundary_filter");

    if (centerOption.clearAddressInput) {
      map.addBoundsChangedCallback(function () {
        jQuery(
          'input.geolocation-geocoder-address[data-source-identifier="' +
            centerOption.identifier +
            '"]'
        ).val("");
      });
    }

    return true;
  };
})(Drupal);
