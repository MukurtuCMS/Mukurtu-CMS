/**
 * @file
 * Control locate.
 */

(function ($, Drupal) {
  "use strict";

  /**
   * Locate control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationControlLocate = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "control_locate",

        /**
         * @param {GeolocationMapInterface} map
         * @param {GeolocationMapFeatureSettings} featureSettings
         */
        function (map, featureSettings) {
          map.addInitializedCallback(function (map) {
            var locateButton = $(
              ".geolocation-map-control .locate",
              map.wrapper
            );

            if (
              navigator.geolocation &&
              window.location.protocol === "https:"
            ) {
              locateButton.click(function (e) {
                navigator.geolocation.getCurrentPosition(function (
                  currentPosition
                ) {
                  var currentLocation = new google.maps.LatLng(
                    currentPosition.coords.latitude,
                    currentPosition.coords.longitude
                  );
                  map.setCenterByCoordinates(
                    currentLocation,
                    currentPosition.coords.accuracy,
                    "google_control_locate"
                  );
                });
                e.preventDefault();
              });
            } else {
              locateButton.remove();
            }
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {},
  };
})(jQuery, Drupal);
