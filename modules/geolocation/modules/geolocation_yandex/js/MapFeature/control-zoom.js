/**
 * @file
 * Control Zoom.
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
  Drupal.behaviors.yandexControlZoom = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'yandex_control_zoom',

        /**
         * @param {GeolocationYandexMap} map - Current map.
         * @param {Object} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          var options = {};

          switch (featureSettings["position"]) {
            case "left":
            case "top":
            case "bottom":
              // Leave the default values.
              options = {};
              break;
            case "right":
              // Size adaptivity will be disabled.
              options = {
                position: {
                  top: "108px",
                  right: "10px",
                  bottom: "auto",
                  left: "auto"
                }
              };
              break;
          }

          map.yandexMap.controls.add('zoomControl', options);

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(Drupal);
