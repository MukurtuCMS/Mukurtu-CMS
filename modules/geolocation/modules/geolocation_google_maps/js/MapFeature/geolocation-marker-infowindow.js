/**
 * @file
 * Marker InfoWindow.
 */

/**
 * @typedef {Object} MarkerInfoWindowSettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {Boolean} infoAutoDisplay
 * @property {Boolean} disableAutoPan
 * @property {Boolean} infoWindowSolitary
 * @property {int} maxWidth
 */

/**
 * @typedef {Object} GoogleInfoWindow
 * @property {Function} open
 * @property {Function} close
 */

/**
 * @property {GoogleInfoWindow} GeolocationGoogleMap.infoWindow
 * @property {function({}):GoogleInfoWindow} GeolocationGoogleMap.InfoWindow
 */

(function (Drupal) {
  "use strict";

  /**
   * Marker InfoWindow.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationMarkerInfoWindow = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "marker_infowindow",

        /**
         * @param {GeolocationGoogleMap} map - Current map.
         * @param {MarkerInfoWindowSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.addMarkerAddedCallback(function (currentMarker) {
            if (typeof currentMarker.locationWrapper === "undefined") {
              return;
            }

            var content =
              currentMarker.locationWrapper.find(".location-content");

            if (content.length < 1) {
              return;
            }
            content = content.html();

            var markerInfoWindow = {
              content: content.toString(),
              disableAutoPan: featureSettings.disableAutoPan,
            };

            if (featureSettings.maxWidth > 0) {
              markerInfoWindow.maxWidth = featureSettings.maxWidth;
            }

            // Set the info popup text.
            var currentInfoWindow = new google.maps.InfoWindow(
              markerInfoWindow
            );

            currentMarker.addListener("click", function () {
              if (featureSettings.infoWindowSolitary) {
                if (typeof map.infoWindow !== "undefined") {
                  map.infoWindow.close();
                }
                map.infoWindow = currentInfoWindow;
              }
              currentInfoWindow.open(map.googleMap, currentMarker);
            });

            if (featureSettings.infoAutoDisplay) {
              if (map.googleMap.get("tilesloading")) {
                google.maps.event.addListenerOnce(
                  map.googleMap,
                  "tilesloaded",
                  function () {
                    google.maps.event.trigger(currentMarker, "click");
                  }
                );
              } else {
                jQuery.each(map.mapMarkers, function (index, currentMarker) {
                  google.maps.event.trigger(currentMarker, "click");
                });
              }
            }
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {},
  };
})(Drupal);
