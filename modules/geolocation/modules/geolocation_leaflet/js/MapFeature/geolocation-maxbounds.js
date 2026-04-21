/**
 * @file
 * Leaflet max bounds.
 */

/**
 * @typedef {Object} LeafletMaxBoundsSettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {String} north
 * @property {String} south
 * @property {String} east
 * @property {String} west
 */

(function (Drupal) {

  'use strict';

  /**
   * LeafletMaxBounds.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationLeafletMaxBounds = {
    attach: function (context, drupalSettings) {

      Drupal.geolocation.executeFeatureOnAllMaps(
        'leaflet_max_bounds',

        /**
         * @param {GeolocationLeafletMap} map - Current map.
         * @param {LeafletMaxBoundsSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.addInitializedCallback(function (map) {
            var east = parseFloat(featureSettings.east);
            var west = parseFloat(featureSettings.west);
            var south = parseFloat(featureSettings.south);
            var north = parseFloat(featureSettings.north);
            if (west > east) {
              east = east + 360;
            }
            var bounds = new L.LatLngBounds([
                [south, west],
                [north, east]
            ]);
            map.leafletMap.setMaxBounds(bounds);
            map.leafletMap.setMinZoom(map.leafletMap.getBoundsZoom(bounds));
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };
})(Drupal);
