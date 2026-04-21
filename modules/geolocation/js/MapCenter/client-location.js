/**
 * @file
 * Client location center.
 */

(function (Drupal) {
  "use strict";

  Drupal.geolocation = Drupal.geolocation || {};
  Drupal.geolocation.mapCenter = Drupal.geolocation.mapCenter || {};

  /**
   * @param {GeolocationMapInterface} map
   * @param {GeolocationCenterOption} centerOption
   */
  Drupal.geolocation.mapCenter.client_location = function (map, centerOption) {
    if (navigator.geolocation) {
      var successCallback = function (position) {
        map.setCenterByCoordinates(
          { lat: position.coords.latitude, lng: position.coords.longitude },
          position.coords.accuracy,
          "map_center_client_location"
        );
        return true;
      };
      navigator.geolocation.getCurrentPosition(successCallback);
    }

    return false;
  };
})(Drupal);
