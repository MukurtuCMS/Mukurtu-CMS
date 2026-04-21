/**
 * @file
 * Control layer.
 */

/**
 * @typedef {Object} ControlLayerSettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {String} defaultLabel
 * @property {Array} tileLayerProviders
 * @property {Array} tileLayerOptions
 * @property {String} position
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Tile layer control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches tile layer control functionality to relevant elements.
   */
  Drupal.behaviors.leafletControlLayer = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'leaflet_control_layer',

        /**
         * @param {GeolocationLeafletMap} map - Current map.
         * @param {ControlLayerSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          var baseMaps = {};
          baseMaps[featureSettings.defaultLabel] = map.tileLayer;
          $.each(featureSettings.tileLayerProviders, function (variant, label) {
            var parts = variant.split('.');
            var provider = parts[0];
            baseMaps[label] = L.tileLayer.provider(variant,
              featureSettings.tileLayerOptions[provider] || {});
          });

          var overlayMaps = {};
          L.control.layers(baseMaps, overlayMaps, {
            position: featureSettings.position
          }).addTo(map.leafletMap);

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(jQuery, Drupal);
