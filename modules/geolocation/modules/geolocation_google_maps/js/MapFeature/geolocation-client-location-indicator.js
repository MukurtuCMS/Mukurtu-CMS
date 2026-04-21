/**
 * @file
 * Client location indicator.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * ClientLocationIndicator.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationClientLocationIndicator = {
    attach: function (context, drupalSettings) {

      Drupal.geolocation.executeFeatureOnAllMaps(
        'client_location_indicator',

        /**
         * @param {GeolocationMapInterface} map
         * @param {GeolocationMapFeatureSettings} featureSettings
         */
        function (map, featureSettings) {
          if (!navigator.geolocation) {
            return true;
          }
          map.addInitializedCallback(function (map) {
            var clientLocationMarker = new google.maps.Marker({
              clickable: false,
              icon: {
                path: google.maps.SymbolPath.CIRCLE,
                fillColor: '#039be5',
                fillOpacity: 1.0,
                scale: 8,
                strokeColor: 'white',
                strokeWeight: 2
              },
              shadow: null,
              zIndex: 999,
              map: map.googleMap,
              position: {lat: 0, lng: 0}
            });

            var indicatorCircle = null;

            setInterval(function () {
              navigator.geolocation.getCurrentPosition(function (currentPosition) {
                var currentLocation = new google.maps.LatLng(currentPosition.coords.latitude, currentPosition.coords.longitude);
                clientLocationMarker.setPosition(currentLocation);

                if (!indicatorCircle) {
                  indicatorCircle = map.addAccuracyIndicatorCircle(currentLocation, parseInt(currentPosition.coords.accuracy.toString()));
                }
                else {
                  indicatorCircle.setCenter(currentLocation);
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

})(jQuery, Drupal);
