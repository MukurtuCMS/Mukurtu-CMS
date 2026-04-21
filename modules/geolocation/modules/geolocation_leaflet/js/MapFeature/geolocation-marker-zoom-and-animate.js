/**
 * @file
 * Marker Zoom By Anchor.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Marker zoom by anchor.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationMarkerZoomByAnchor = {
    attach: function (context, drupalSettings) {

      $(once('geolocation-marker-zoom-by-anchor', 'a.geolocation-marker-zoom')).click(function (e) {
        e.preventDefault();
        var markerAnchor = $(this).attr('href').split('#').pop();
        Drupal.geolocation.executeFeatureOnAllMaps(
          'leaflet_marker_zoom_and_animate',

          /**
           * @param {GeolocationLeafletMap} map - Current map.
           * @param {MarkerIconSettings} featureSettings - Settings for current feature.
           */
          function (map, featureSettings) {
            $.each(map.mapMarkers, function (index, marker) {
              if (marker.locationWrapper.data('marker-zoom-anchor-id').replace(/\s/g, "") === markerAnchor) {
                $('html, body').animate({
                  scrollTop: map.wrapper.offset().top
                }, 'slow');

                var latLngs = [ marker.getLatLng() ];
                var markerBounds = L.latLngBounds(latLngs);
                map.leafletMap.fitBounds(markerBounds);

                marker.bounce({duration: 2000, height: 100});
              }
            });

            return false;
          },
          drupalSettings
        );
      });
    },
    detach: function (context, drupalSettings) {}
  };
})(jQuery, Drupal);
