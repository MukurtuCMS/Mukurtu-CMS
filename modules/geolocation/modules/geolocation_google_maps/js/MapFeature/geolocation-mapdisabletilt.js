/**
 * @file
 * Disable tilt.
 */

(function (Drupal) {
  "use strict";

  /**
   * Enable Tilt.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map tilt functionality to relevant elements.
   */
  Drupal.behaviors.geolocationDisableTilt = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "map_disable_tilt",

        /**
         * @param {GeolocationMapInterface} map - Current map.
         * @param {GeolocationMapFeatureSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.addInitializedCallback(function (map) {
            map.googleMap.setTilt(0);
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {},
  };
})(Drupal);
