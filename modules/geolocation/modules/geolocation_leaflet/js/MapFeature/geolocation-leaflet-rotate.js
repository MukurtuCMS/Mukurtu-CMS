/**
 * @file
 * Marker Clusterer.
 */

(function (Drupal) {
  'use strict';

  /**
   * Marker Clusterer.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map marker cluster functionality to relevant elements.
   */
  Drupal.behaviors.leafletRotate = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
          'leaflet_rotate',
          function (map, featureSettings) {
            L.Util.setOptions(map.leafletMap, {
              rotateControl: featureSettings.display_control
            });

            map.leafletMap.setBearing(featureSettings.bearing);
            map.leafletMap.touchRotate.enable();

            return true;
          },
          drupalSettings
      );
    },
    detach: function (context, drupalSettings) { }
  };
})(Drupal);
