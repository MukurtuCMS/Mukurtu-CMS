/**
 * @file
 * Marker Popup.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Marker Popup.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map marker popup functionality to relevant elements.
   */
  Drupal.behaviors.baiduMarkerInfowindow = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'baidu_marker_infowindow',

        /**
         * @param {GeolocationBaiduMap} map - Current map.
         * @param {GeolocationMapFeatureSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          var geolocationBaiduInfowindowHandler = function (currentMarker) {
            if (typeof (currentMarker.locationWrapper) === 'undefined') {
              return;
            }

            var content = currentMarker.locationWrapper.find('.location-content');

            if (content.length < 1) {
              return;
            }

            var opts = {
              width : 200,
              height: 100,
              title : currentMarker.title
            };
            var infoWindow = new BMap.InfoWindow(content.html(), opts);
            currentMarker.addEventListener("click", function () {
              map.baiduMap.openInfoWindow(infoWindow, currentMarker.getPosition());
            });
          };

          map.addPopulatedCallback(function (map) {
            $.each(map.mapMarkers, function (index, currentMarker) {
              geolocationBaiduInfowindowHandler(currentMarker);
            });
          });

          map.addMarkerAddedCallback(function (currentMarker) {
            geolocationBaiduInfowindowHandler(currentMarker);
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };
})(jQuery, Drupal);
