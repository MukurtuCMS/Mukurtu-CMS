/**
 * @file
 * Leaflet tiles.
 */

/**
 * @typedef {Object} TileLayerOverlaySettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {String} tileLayerOverlay
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
   *   Attaches common map tile layer overlay functionality to relevant elements.
   */
  Drupal.behaviors.geolocationLeafletMapTileLayerOverlay = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'leaflet_tile_layer_overlay',

        /**
         * @param {GeolocationLeafletMap} map - Current map.
         * @param {TileLayerOverlaySettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          L.tileLayer.provider(featureSettings.tileLayerOverlay,
            featureSettings.tileLayerOptions
          ).addTo(map.leafletMap).bringToFront();

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };
})(Drupal);
