/**
 * @file
 * Drawing.
 */

/**
 * @typedef {Object} DrawingSettings
 *
 * @property {String} enable
 * @property {Object} settings - Settings
 * @property {Boolean|String} settings.polyline - Draw polyline
 * @property {Boolean|String} settings.polygon - Draw polygon
 */

(function ($, Drupal) {
  "use strict";

  /**
   * DrawingSettings.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationDrawing = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "drawing",

        /**
         * @param {GeolocationGoogleMap} map - Current map.
         * @param {DrawingSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.addInitializedCallback(function (map) {
            var locations = [];

            $("#" + map.id, context)
              .find(".geolocation-location")
              .each(function (index, locationElement) {
                var location = $(locationElement);
                locations.push(
                  new google.maps.LatLng(
                    Number(location.data("lat")),
                    Number(location.data("lng"))
                  )
                );
              });

            if (!locations.length) {
              return;
            }

            var drawingSettings = featureSettings.settings;

            if (drawingSettings.polygon && drawingSettings.polygon !== "0") {
              var polygon = new google.maps.Polygon({
                paths: locations,
                strokeColor: drawingSettings.strokeColor,
                strokeOpacity: drawingSettings.strokeOpacity,
                strokeWeight: drawingSettings.strokeWeight,
                geodesic: drawingSettings.geodesic,
                fillColor: drawingSettings.fillColor,
                fillOpacity: drawingSettings.fillOpacity,
              });
              polygon.setMap(map.googleMap);
            }

            if (drawingSettings.polyline && drawingSettings.polyline !== "0") {
              var polyline = new google.maps.Polyline({
                path: locations,
                strokeColor: drawingSettings.strokeColor,
                strokeOpacity: drawingSettings.strokeOpacity,
                strokeWeight: drawingSettings.strokeWeight,
                geodesic: drawingSettings.geodesic,
              });
              polyline.setMap(map.googleMap);
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
