/**
 * @file
 * Marker Icon.
 */

/**
 * @typedef {Object} LeafletMarkerIconSettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {String} markerIconPath
 * @property {Array} iconSize
 * @property {Number} iconSize.width
 * @property {Number} iconSize.height
 * @property {Array} iconAnchor
 * @property {Number} iconAnchor.x
 * @property {Number} iconAnchor.y
 * @property {Array} popupAnchor
 * @property {Number} popupAnchor.x
 * @property {Number} popupAnchor.y
 * @property {String} markerShadowPath
 * @property {Array} shadowSize
 * @property {Number} shadowSize.width
 * @property {Number} shadowSize.height
 * @property {Array} shadowAnchor
 * @property {Number} shadowAnchor.x
 * @property {Number} shadowAnchor.y
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Marker Icon Adjustment.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches map marker icon adjustment functionality to relevant elements.
   */
  Drupal.behaviors.leafletMarkerIcon = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'leaflet_marker_icon',

        /**
         * @param {GeolocationLeafletMap} map - Current map.
         * @param {LeafletMarkerIconSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {

          var geolocationLeafletIconHandler = function (currentMarker) {

            var iconUrl;
            if (typeof currentMarker.locationWrapper !== 'undefined') {
              var currentIcon = currentMarker.locationWrapper.data('icon');
            }

            if (typeof currentIcon === 'undefined') {
              if (typeof featureSettings.markerIconPath === 'string') {
                iconUrl = featureSettings.markerIconPath;
              }
              else {
                return;
              }
            }
            else {
              iconUrl = currentIcon;
            }

            var iconOptions = {
              iconUrl: iconUrl,
              shadowUrl: featureSettings.markerShadowPath
            };

            if (featureSettings.iconSize.width && featureSettings.iconSize.height) {
              $.extend(iconOptions, {iconSize: [featureSettings.iconSize.width, featureSettings.iconSize.height]});
            }

            if (featureSettings.shadowSize.width && featureSettings.shadowSize.height) {
              $.extend(iconOptions, {shadowSize: [featureSettings.shadowSize.width, featureSettings.shadowSize.height]});
            }

            if (featureSettings.iconAnchor.x || featureSettings.iconAnchor.y) {
              $.extend(iconOptions, {iconAnchor: [featureSettings.iconAnchor.x, featureSettings.iconAnchor.y]});
            }

            if (featureSettings.shadowAnchor.x || featureSettings.shadowAnchor.y) {
              $.extend(iconOptions, {shadowAnchor: [featureSettings.shadowAnchor.x, featureSettings.shadowAnchor.y]});
            }

            if (featureSettings.popupAnchor.x || featureSettings.popupAnchor.y) {
              $.extend(iconOptions, {popupAnchor: [featureSettings.popupAnchor.x, featureSettings.popupAnchor.y]});
            }

            currentMarker.setIcon(L.icon(iconOptions));
          };

          map.addPopulatedCallback(function (map) {
            $.each(map.mapMarkers, function (index, currentMarker) {
              geolocationLeafletIconHandler(currentMarker);
            });
          });

          map.addMarkerAddedCallback(function (currentMarker) {
            geolocationLeafletIconHandler(currentMarker);
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };
})(jQuery, Drupal);
