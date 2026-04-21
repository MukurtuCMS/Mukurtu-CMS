/**
 * @file
 * Gesture handling.
 */

(function (Drupal) {

  'use strict';

  /**
   * Gesture handling.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map gesture handling functionality to relevant elements.
   */
  Drupal.behaviors.leafletGestureHandling = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'leaflet_gesture_handling',

        /**
         * @param {GeolocationLeafletMap} map - Current map.
         * @param {GeolocationMapFeatureSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          L.Util.setOptions(map.leafletMap, {
            gestureHandlingOptions: {
              duration: 1000
            }
          });
          map.leafletMap['gestureHandling'].enable();

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(Drupal);
