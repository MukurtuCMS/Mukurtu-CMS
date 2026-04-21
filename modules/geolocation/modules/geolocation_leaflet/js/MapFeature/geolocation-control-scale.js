/**
 * @file
 * Control scale.
 */

/**
 * @typedef {Object} ControlScaleSettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {String} position
 * @property {Boolean} metric
 * @property {Boolean} imperial
 */

(function (Drupal) {

  'use strict';

  /**
   * Scale control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map scale functionality to relevant elements.
   */
  Drupal.behaviors.leafletControlScale = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'leaflet_control_scale',

        /**
         * @param {GeolocationLeafletMap} map - Current map.
         * @param {ControlScaleSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          L.control.scale({
            position: featureSettings.position,
            metric: featureSettings.metric,
            imperial: featureSettings.imperial
          }).addTo(map.leafletMap);

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(Drupal);
