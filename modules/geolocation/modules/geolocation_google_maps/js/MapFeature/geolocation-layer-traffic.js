/**
 * @file
 * Layer traffic.
 */

(function (Drupal) {
  "use strict";

  /**
   * Layer traffic.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches traffic layer.
   */
  Drupal.behaviors.geolocationGoogleMapsLayerTraffic = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "google_maps_layer_traffic",

        /**
         * @param {GeolocationMapInterface} map - Current map.
         * @param {GeolocationMapFeatureSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.addInitializedCallback(function (map) {
            var trafficLayer = new google.maps.TrafficLayer();
            trafficLayer.setMap(map.googleMap);
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {},
  };
})(Drupal);
