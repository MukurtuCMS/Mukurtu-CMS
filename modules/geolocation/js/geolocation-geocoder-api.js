/**
 * @file
 * Javascript for the plugin-based geocoder function.
 */

/**
 * Callback when map is clicked.
 *
 * @callback GeolocationGeocoderResultCallback
 * @callback GeolocationGeocoderClearCallback
 *
 * @param {Object} address - Address.
 */

/**
 * Geocoder API.
 */
(function ($, Drupal) {
  "use strict";

  Drupal.geolocation = Drupal.geolocation || {};

  Drupal.geolocation.geocoder = Drupal.geolocation.geocoder || {};

  /**
   * Provides the callback that is called when geocoded results are found loads.
   *
   * @param {google.maps.GeocoderResult} result - first returned address
   * @param {string} elementId - Source ID.
   */
  Drupal.geolocation.geocoder.resultCallback = function (result, elementId) {
    Drupal.geolocation.geocoder.resultCallbacks =
      Drupal.geolocation.geocoder.resultCallbacks || [];
    $.each(
      Drupal.geolocation.geocoder.resultCallbacks,
      function (index, callbackContainer) {
        if (callbackContainer.elementId === elementId) {
          callbackContainer.callback(result);
        }
      }
    );
  };

  /**
   * Adds a callback that will be called when results are found.
   *
   * @param {GeolocationGeocoderResultCallback} callback - The callback
   * @param {string} elementId - Identify source of result by its element ID.
   */
  Drupal.geolocation.geocoder.addResultCallback = function (
    callback,
    elementId
  ) {
    if (typeof elementId === "undefined") {
      return;
    }
    Drupal.geolocation.geocoder.resultCallbacks =
      Drupal.geolocation.geocoder.resultCallbacks || [];
    Drupal.geolocation.geocoder.resultCallbacks.push({
      callback: callback,
      elementId: elementId,
    });
  };

  /**
   * Provides the callback that is called when results become invalid loads.
   *
   * @param {string} elementId - Source ID.
   */
  Drupal.geolocation.geocoder.clearCallback = function (elementId) {
    Drupal.geolocation.geocoder.clearCallbacks =
      Drupal.geolocation.geocoder.clearCallbacks || [];
    $.each(
      Drupal.geolocation.geocoder.clearCallbacks,
      function (index, callbackContainer) {
        if (callbackContainer.elementId === elementId) {
          callbackContainer.callback();
        }
      }
    );
  };

  /**
   * Adds a callback that will be called when results should be cleared.
   *
   * @param {GeolocationGeocoderClearCallback} callback - The callback
   * @param {string} elementId - Identify source of result by its element ID.
   */
  Drupal.geolocation.geocoder.addClearCallback = function (
    callback,
    elementId
  ) {
    if (typeof elementId === "undefined") {
      return;
    }
    Drupal.geolocation.geocoder.clearCallbacks =
      Drupal.geolocation.geocoder.clearCallbacks || [];
    Drupal.geolocation.geocoder.clearCallbacks.push({
      callback: callback,
      elementId: elementId,
    });
  };
})(jQuery, Drupal);
