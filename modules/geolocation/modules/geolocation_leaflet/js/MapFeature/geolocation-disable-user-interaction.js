/**
 * @file
 * Gesture handling.
 */

(function (Drupal) {

  'use strict';

  /**
   * Map interaction disable handling.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map gesture handling functionality to relevant elements.
   */
  Drupal.behaviors.leafletGestureHandling = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'leaflet_disable_user_interaction',

        /**
         * @param {GeolocationLeafletMap} map - Current map.
         * @param {GeolocationMapFeatureSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {

          map.leafletMap.dragging.disable();
          map.leafletMap.touchZoom.disable();
          map.leafletMap.doubleClickZoom.disable();
          map.leafletMap.scrollWheelZoom.disable();

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(Drupal);
