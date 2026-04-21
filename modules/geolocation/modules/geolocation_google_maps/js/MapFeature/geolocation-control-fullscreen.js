/**
 * @file
 * Control Fullscreen.
 */

/**
 * @typedef {Object} ControlFullscreenSettings
 *
 * @extends {GeolocationMapFeatureSettings}

 * @property {String} position
 * @property {String} behavior
 */

(function (Drupal) {

  'use strict';

  /**
   * Fullscreen control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationFullScreenControl = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'control_fullscreen',

        /**
         * @param {GeolocationGoogleMap} map - Current map.
         * @param {ControlFullscreenSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.addPopulatedCallback(function (map) {
            var options = {
              fullscreenControlOptions: {
                position: google.maps.ControlPosition[featureSettings.position]
              }
            };

            if (featureSettings.behavior === 'always') {
              options.fullscreenControl = true;
            }
            else {
              options.fullscreenControl = undefined;
            }

            map.googleMap.setOptions(options);
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(Drupal);
