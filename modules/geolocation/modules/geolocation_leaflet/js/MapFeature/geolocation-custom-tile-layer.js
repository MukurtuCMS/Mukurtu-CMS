/**
 * @file
 * Custom tile layer.
 */

/**
 * @typedef {Object} CustomTileLayerSettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {String} tileLayerUrl
 * @property {String} tileLayerAttribution
 * @property {String} tileLayerSubdomains
 * @property {Number} tileLayerZoom
 */

(function (Drupal) {

  'use strict';

  /**
   * Custom Tile Layer.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches Custom Tile Layer functionality to relevant elements.
   */
  Drupal.behaviors.leafletCustomTileLayer = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'leaflet_custom_tile_layer',

        /**
         * @param {GeolocationLeafletMap} map - Current map.
         * @param {CustomTileLayerSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.tileLayer.remove();
          map.tileLayer = L.tileLayer(featureSettings.tileLayerUrl, {
            attribution: featureSettings.tileLayerAttribution,
            subdomains: featureSettings.tileLayerSubdomains,
            maxZoom: featureSettings.tileLayerZoom
          }).addTo(map.leafletMap);

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };
})(Drupal);
