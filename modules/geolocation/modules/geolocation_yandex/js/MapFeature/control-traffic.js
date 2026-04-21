/**
 * @file
 * Control traffic.
 */

(function (Drupal) {

  'use strict';

  /**
   * Search control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map traffic functionality to relevant elements.
   */
  Drupal.behaviors.yandexControlTraffic = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'yandex_control_traffic',

        /**
         * @param {GeolocationYandexMap} map - Current map.
         * @param {Object} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.yandexMap.controls.add('trafficControl');

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(Drupal);
