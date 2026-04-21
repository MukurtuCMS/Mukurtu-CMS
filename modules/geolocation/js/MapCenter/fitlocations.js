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
   * @param {Boolean} centerOption.settings.reset_zoom
   * @param {Boolean} centerOption.settings.min_zoom
   */
  Drupal.geolocation.mapCenter.fit_bounds = function (map, centerOption) {
    if (typeof map.mapMarkers === "undefined") {
      return false;
    }

    if (map.mapMarkers.length === 0) {
      return false;
    }

    map.fitMapToMarkers();

    if (centerOption.settings.reset_zoom) {
      map.setZoom(undefined, true);
    } else if (centerOption.settings.min_zoom) {
      map.getZoom().then(function (zoom) {
        if (centerOption.settings.min_zoom < zoom) {
          map.setZoom(centerOption.settings.min_zoom);
        }
      });
    }

    return true;
  };
})(Drupal);
