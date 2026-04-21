/**
 * @file
 * Control Recenter.
 */

(function ($, Drupal) {
  "use strict";

  /**
   * Recenter control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationControlRecenter = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "control_recenter",

        /**
         * @param {GeolocationMapInterface} map
         * @param {GeolocationMapFeatureSettings} featureSettings
         */
        function (map, featureSettings) {
          map.addInitializedCallback(function (map) {
            var recenterButton = $(
              ".geolocation-map-control .recenter",
              map.wrapper
            );
            recenterButton.click(function (e) {
              map.setCenter();
              e.preventDefault();
            });
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {},
  };
})(jQuery, Drupal);
