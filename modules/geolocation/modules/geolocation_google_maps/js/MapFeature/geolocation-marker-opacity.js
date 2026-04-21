/**
 * @file
 * Marker Opacity.
 */

/**
 * @typedef {Object} MarkerOpacitySettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {String} opacity
 */

(function (Drupal) {
  "use strict";

  /**
   * Google MarkerOpacity.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationMarkerOpacity = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "marker_opacity",

        /**
         * @param {GeolocationGoogleMap} map - Current map.
         * @param {MarkerOpacitySettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.addMarkerAddedCallback(function (currentMarker) {
            currentMarker.setOpacity(parseFloat(featureSettings.opacity));
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {},
  };
})(Drupal);
