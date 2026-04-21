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
  Drupal.behaviors.leafletMarkerClusterer = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'leaflet_marker_clusterer',

        /**
         * @param {GeolocationLeafletMap} map - Current map.
         * @param {GeolocationMapFeatureSettings} featureSettings - Settings for current feature.
         * @param {String} featureSettings.zoomToBoundsOnClick - Settings for current feature.
         * @param {String} featureSettings.showCoverageOnHover - Settings for current feature.
         * @param {int} featureSettings.disableClusteringAtZoom - Settings for current feature.
         * @param {Object} featureSettings.customMarkerSettings - Settings for current feature.
         *
         * @see https://github.com/Leaflet/Leaflet.markercluster
         */
        function (map, featureSettings) {
          var options = {
            showCoverageOnHover: false,
            zoomToBoundsOnClick: false,
            disableClusteringAtZoom: null
          };

          if (featureSettings.zoomToBoundsOnClick) {
            options.zoomToBoundsOnClick = true;
          }
          if (featureSettings.showCoverageOnHover) {
            options.showCoverageOnHover = true;
          }
          if (featureSettings.disableClusteringAtZoom) {
            options.disableClusteringAtZoom = featureSettings.disableClusteringAtZoom;
          }
          if (featureSettings.customMarkerSettings) {
            options.iconCreateFunction = function (cluster) {
              var childCount = cluster.getChildCount();
              var customMarkers = featureSettings.customMarkerSettings;
              var className = ' marker-cluster-';
              var radius = 40;

              for (var size in customMarkers) {
                if (childCount < customMarkers[size].limit) {
                  className += size;
                  radius = customMarkers[size].radius;
                  break;
                }
              }

              return new L.DivIcon({
                html: '<div><span>' + childCount + '</span></div>',
                className: 'marker-cluster' + className,
                iconSize: new L.Point(radius, radius)
              });
            };
          }

          var cluster = L.markerClusterGroup(options);

          map.leafletMap.removeLayer(map.markerLayer);
          cluster.addLayer(map.markerLayer);

          map.leafletMap.addLayer(cluster);

          map.addMarkerAddedCallback(function (currentMarker) {
            cluster.addLayer(currentMarker);
          });

          map.addMarkerRemoveCallback(function (marker) {
            cluster.removeLayer(marker);
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) { }
  };
})(Drupal);
