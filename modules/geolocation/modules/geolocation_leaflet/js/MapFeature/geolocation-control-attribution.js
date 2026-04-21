/**
 * @file
 * Control Zoom.
 */

/**
 * @typedef {Object} ControlAttributionSettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {String} prefix
 * @property {String} position
 */

(function (Drupal) {

  'use strict';

  /**
   * Zoom control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map zoom functionality to relevant elements.
   */
  Drupal.behaviors.leafletControlAttribution = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'leaflet_control_attribution',

        /**
         * @param {GeolocationLeafletMap} map - Current map.
         * @param {ControlAttributionSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          L.control.attribution({
            prefix: featureSettings.prefix + ' | &copy; <a href="https://osm.org/copyright">OpenStreetMap</a> contributors',
            position: featureSettings.position
          }).addTo(map.leafletMap);

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(Drupal);
