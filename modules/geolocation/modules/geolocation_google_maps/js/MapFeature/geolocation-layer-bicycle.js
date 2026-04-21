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
   *   Attaches bicycle layer.
   */
  Drupal.behaviors.geolocationGoogleMapsLayerBicycle = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "google_maps_layer_bicycle",

        /**
         * @param {GeolocationMapInterface} map - Current map.
         * @param {GeolocationMapFeatureSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.addInitializedCallback(function (map) {
            var bikeLayer = new google.maps.BicyclingLayer();
            bikeLayer.setMap(map.googleMap);
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {},
  };
})(Drupal);
