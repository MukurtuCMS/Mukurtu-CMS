/**
 * @file
 * Control Street View.
 */

/**
 * @typedef {Object} ControlStreetViewSettings
 *
 * @extends {GeolocationMapFeatureSettings}

 * @property {String} position
 * @property {String} behavior
 */

(function (Drupal) {
  "use strict";

  /**
   * Streetview control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationStreetViewControl = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "control_streetview",

        /**
         * @param {GeolocationGoogleMap} map - Current map.
         * @param {ControlStreetViewSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.addPopulatedCallback(function (map) {
            var options = {
              streetViewControlOptions: {
                position: google.maps.ControlPosition[featureSettings.position],
              },
            };

            if (featureSettings.behavior === "always") {
              options.streetViewControl = true;
            } else {
              options.streetViewControl = undefined;
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
