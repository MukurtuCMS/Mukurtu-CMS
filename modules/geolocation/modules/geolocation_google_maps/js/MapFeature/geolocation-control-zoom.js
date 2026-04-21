/**
 * @file
 * Zoom Control.
 */

/**
 * @typedef {Object} ControlZoomSettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {String} behavior
 * @property {String} position
 * @property {String} style
 */

(function (Drupal) {
  "use strict";

  /**
   * Zoom control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationZoomControl = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "control_zoom",

        /**
         * @param {GeolocationGoogleMap} map - Current map.
         * @param {ControlZoomSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.addPopulatedCallback(function (map) {
            var options = {
              zoomControlOptions: {
                position: google.maps.ControlPosition[featureSettings.position],
                style: google.maps.ZoomControlStyle[featureSettings.style],
              },
            };

            if (featureSettings.behavior === "always") {
              options.zoomControl = true;
            } else {
              options.zoomControl = undefined;
            }
            map.googleMap.setOptions(options);
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {},
  };
})(Drupal);
