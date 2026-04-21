/**
 * @file
 * Disable POI.
 */

(function (Drupal) {
  "use strict";

  /**
   * Disable User interaction.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationDisableUserInteraction = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "map_disable_user_interaction",

        /**
         * @param {GeolocationGoogleMap} map - Current map.
         * @param {GeolocationMapFeatureSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.addInitializedCallback(function (map) {
            map.googleMap.setOptions({
              gestureHandling: "none",
              zoomControl: false,
            });
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {},
  };
})(Drupal);
