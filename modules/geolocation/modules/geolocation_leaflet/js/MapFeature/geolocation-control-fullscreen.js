/**
 * @file
 * Control fullscreen.
 */

(function (Drupal) {

  'use strict';

  /**
   * Fullscreen control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map fullscreen functionality to relevant elements.
   */
  Drupal.behaviors.leafletControlFullscreen = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'leaflet_control_fullscreen',

        /**
         * @param {GeolocationLeafletMap} map - Current map.
         * @param {GeolocationMapFeatureSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.leafletMap.addControl(new L.Control.Fullscreen({
            position: featureSettings.position,
            title: {
              "false": Drupal.t("View Fullscreen"),
              "true": Drupal.t("Exit Fullscreen")
            }
          }));

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(Drupal);
