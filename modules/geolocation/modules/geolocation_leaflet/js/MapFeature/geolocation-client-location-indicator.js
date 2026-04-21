/**
 * @file
 * Client location indicator.
 */

(function (Drupal) {

  'use strict';

  /**
   * ClientLocationIndicator.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches client location indicator functionality to relevant elements.
   */
  Drupal.behaviors.leafletClientLocationIndicator = {
    attach: function (context, drupalSettings) {

      Drupal.geolocation.executeFeatureOnAllMaps(
        'leaflet_client_location_indicator',

        /**
         * @param {GeolocationMapInterface} map
         * @param {GeolocationMapFeatureSettings} featureSettings
         */
        function (map, featureSettings) {
          if (!navigator.geolocation) {
            return true;
          }
          map.addInitializedCallback(function (map) {
            var clientLocationMarker = L.circleMarker([0, 0], {
              interactive: false,
              radius: 8,
              fillColor: "#039be5",
              fillOpacity: 1.0,
              color: "white",
              weight: 2
            }).addTo(map.leafletMap);

            var indicatorCircle = null;

            setInterval(function () {
              navigator.geolocation.getCurrentPosition(function (currentPosition) {
                var currentLocation = L.latLng(currentPosition.coords.latitude, currentPosition.coords.longitude);
                clientLocationMarker.setLatLng(currentLocation);

                if (!indicatorCircle) {
                  indicatorCircle = map.addAccuracyIndicatorCircle(currentLocation, parseInt(currentPosition.coords.accuracy.toString()));
                }
                else {
                  indicatorCircle.setLatLng(currentLocation);
                  indicatorCircle.setRadius(parseInt(currentPosition.coords.accuracy.toString()));
                }
              });
            }, 5000);
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(Drupal);
