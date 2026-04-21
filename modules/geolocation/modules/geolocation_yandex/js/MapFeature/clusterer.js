/**
 * @file
 * Marker Clusterer.
 */

(function (Drupal) {

  'use strict';

  /* global ymaps */

  /**
   * Marker Clusterer.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map marker cluster functionality to relevant elements.
   */
  Drupal.behaviors.yandexClusterer = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'yandex_clusterer',

        /**
         * @param {GeolocationYandexMap} map - Current map.
         * @param {Object} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {

          var clusterObjects = [];

          map.yandexMap.geoObjects.each(function (el, i) {
            if (el.geometry === null) {
              return true;
            }
            if (typeof el.geometry === 'undefined') {
              return true;
            }

            clusterObjects[i] = new ymaps.GeoObject({
              geometry: el.geometry
            });
          });

          var clusterer = new ymaps.Clusterer();
          clusterer.add(clusterObjects);
          map.yandexMap.geoObjects.add(clusterer);

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };
})(Drupal);
