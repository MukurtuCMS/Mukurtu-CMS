/**
 * @file
 * WMS.
 */

/**
 * @typedef {Object} WMSSettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {String} url
 * @property {String} version
 * @property {String} layers
 * @property {String} styles
 * @property {String} srs
 * @property {String} format
 * @property {Boolean} transparent
 * @property {Boolean} identify
 */

(function (Drupal) {

  'use strict';

  /**
   * Web Map services.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches web map services functionality to relevant elements.
   */
  Drupal.behaviors.leafletWMS = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'leaflet_wms',

        /**
         * @param {GeolocationLeafletMap} map - Current map.
         * @param {WMSSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          var source = L.WMS.source(featureSettings.url, {
            'version': featureSettings.version,
            'styles': featureSettings.styles,
            'srs': featureSettings.srs,
            'format': featureSettings.format,
            'transparent': !!featureSettings.transparent,
            'identify': !!featureSettings.identify
          });
          source.getLayer(featureSettings.layers).addTo(map.leafletMap);

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(Drupal);
