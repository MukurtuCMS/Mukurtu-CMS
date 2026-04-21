/**
 * @file
 * Search.
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
   *   Attaches common map search functionality to relevant elements.
   */
  Drupal.behaviors.yandexControlSearch = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'yandex_control_search',

        /**
         * @param {GeolocationYandexMap} map - Current map.
         * @param {Object} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          var searchControl = new ymaps.control.SearchControl({
            options: { noPlacemark: true }
          });
          map.yandexMap.controls.add(searchControl);

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(Drupal);
