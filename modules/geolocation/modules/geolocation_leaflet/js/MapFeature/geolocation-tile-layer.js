/**
 * @file
 * Tile layer.
 */

/**
 * @typedef {Object} TileLayerSettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {String} tileLayerProvider
 * @property {String} tileLayerOptions
 */

(function (Drupal) {

  'use strict';

  /**
   * TileLayerSettings.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches TileLayerSettings functionality to relevant elements.
   */
  Drupal.behaviors.leafletTileLayer = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'leaflet_tile_layer',

        /**
         * @param {GeolocationLeafletMap} map - Current map.
         * @param {TileLayerSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.tileLayer.remove();
          map.tileLayer = L.tileLayer.provider(featureSettings.tileLayerProvider,
            featureSettings.tileLayerOptions
          ).addTo(map.leafletMap);

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };
})(Drupal);
