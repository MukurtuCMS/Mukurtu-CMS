/**
 * @file
 * Marker Clusterer.
 */

/**
 * @typedef {Object} MarkerClustererSettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {String} imagePath
 * @property {Object} styles
 * @property {Number} maxZoom
 * @property {Number} gridSize
 * @property {Boolean} zoomOnClick
 * @property {Number} averageCenter
 * @property {Number} minimumClusterSize
 */

(function (Drupal) {
  "use strict";

  /**
   * MarkerClusterer.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationMarkerClusterer = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "marker_clusterer",

        /**
         * @param {GeolocationGoogleMap} map - Current map.
         * @param {MarkerClustererSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          if (typeof MarkerClusterer === "undefined") {
            return;
          }

          /* global MarkerClusterer */

          var imagePath = "";
          if (featureSettings.imagePath) {
            imagePath = featureSettings.imagePath;
          } else {
            imagePath =
              "https://cdn.jsdelivr.net/gh/googlemaps/js-marker-clusterer@gh-pages/images/m";
          }

          var markerClustererStyles = {};
          if (typeof featureSettings.styles !== "undefined") {
            markerClustererStyles = featureSettings.styles;
          }

          map.addPopulatedCallback(function (map) {
            if (typeof map.markerClusterer === "undefined") {
              map.markerClusterer = new MarkerClusterer(
                map.googleMap,
                map.mapMarkers,
                {
                  imagePath: imagePath,
                  styles: markerClustererStyles,
                  maxZoom: featureSettings.maxZoom,
                  gridSize: featureSettings.gridSize,
                  zoomOnClick: featureSettings.zoomOnClick,
                  averageCenter: featureSettings.averageCenter,
                  minimumClusterSize: featureSettings.minimumClusterSize,
                }
              );
            }

            map.addMarkerAddedCallback(function (marker) {
              map.markerClusterer.addMarker(marker);
            });

            map.addMarkerRemoveCallback(function (marker) {
              map.markerClusterer.removeMarker(marker);
            });
          });

          map.addUpdatedCallback(function (map, mapSettings) {
            if (typeof map.markerClusterer !== "undefined") {
              map.markerClusterer.clearMarkers();
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
