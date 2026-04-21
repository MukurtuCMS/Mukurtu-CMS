/**
 * @file
 * Balloon.
 */

/**
 * @typedef {Object} YandexBalloonSettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {Boolean} infoAutoDisplay
 * @property {Boolean} disableAutoPan
 * @property {int} maxWidth
 * @property {String} panelMaxMapArea
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Marker Balloon.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.yandexBalloon = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
          'yandex_balloon',

          /**
           * @param {GeolocationYandexMap} map - Current map.
           * @param {YandexBalloonSettings} featureSettings - Settings for current feature.
           * @return {boolean} Executed successfully.
           */
          function (map, featureSettings) {
            var yandexBalloonHandler = function (currentMarker) {

              if (typeof (currentMarker.locationWrapper) === 'undefined') {
                return;
              }

              var content = currentMarker.locationWrapper.find('.location-content');
              if (content.length < 1) {
                return;
              }
              content = content.html();

              currentMarker.properties.set('balloonContent', content.toString());

              if (featureSettings.disableAutoPan) {
                currentMarker.options.set('balloonAutoPan', false);
              }

              if (featureSettings.maxWidth > 0) {
                currentMarker.options.set('balloonMaxWidth', featureSettings.maxWidth);
              }

              if (featureSettings.panelMaxMapArea !== '') {
                currentMarker.options.set('balloonPanelMaxMapArea', featureSettings.panelMaxMapArea);
              }

              if (featureSettings.infoAutoDisplay) {
                currentMarker.balloon.open();
              }
            };

            map.addPopulatedCallback(function () {
              $.each(map.mapMarkers, function (index, currentMarker) {
                yandexBalloonHandler(currentMarker);
              });

            });

            map.addMarkerAddedCallback(function (currentMarker) {
              yandexBalloonHandler(currentMarker);
            });

            return true;
          },
          drupalSettings
      );
    },
    detach: function (context, drupalSettings) {
    }
  };
})(jQuery, Drupal);
