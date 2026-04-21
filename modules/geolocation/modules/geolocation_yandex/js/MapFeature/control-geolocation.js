/**
 * @file
 * Control geolocation.
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
   *   Attaches common map geolocation functionality to relevant elements.
   */
  Drupal.behaviors.yandexControlGeolocation = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'yandex_control_geolocation',

        /**
         * @param {GeolocationYandexMap} map - Current map.
         * @param {Object} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          var geolocationControl = new ymaps.control.GeolocationControl({
            options: { noPlacemark: true }
          });

          map.yandexMap.controls.add(geolocationControl);

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(Drupal);
