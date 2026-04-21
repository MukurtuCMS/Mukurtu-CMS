/**
 * @file
 * Layer transit.
 */

(function (Drupal) {
  "use strict";

  /**
   * Layer traffic.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches transit layer.
   */
  Drupal.behaviors.geolocationGoogleMapsLayerTransit = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "google_maps_layer_transit",

        /**
         * @param {GeolocationMapInterface} map - Current map.
         * @param {GeolocationMapFeatureSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.addInitializedCallback(function (map) {
            var transitLayer = new google.maps.TransitLayer();
            transitLayer.setMap(map.googleMap);
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {},
  };
})(Drupal);
