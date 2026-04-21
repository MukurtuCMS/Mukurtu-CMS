/**
 * @file
 * Control type.
 */

(function (Drupal) {

  'use strict';

  /* global ymaps */

  /**
   * Search control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map type functionality to relevant elements.
   */
  Drupal.behaviors.yandexControlType = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'yandex_control_type',

        /**
         * @param {GeolocationYandexMap} map - Current map.
         * @param {Object} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.yandexMap.controls.add(new ymaps.control.TypeSelector());

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(Drupal);
