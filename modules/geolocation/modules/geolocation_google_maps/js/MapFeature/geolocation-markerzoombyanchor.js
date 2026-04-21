/**
 * @file
 * Marker Zoom By Anchor.
 */

(function ($, Drupal) {
  "use strict";

  /**
   * Google MarkerIcon.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationMarkerZoomByAnchor = {
    attach: function (context, drupalSettings) {
      $(once("geolocation-marker-zoom-by-anchor", "a.geolocation-marker-zoom"))
        .click(function (e) {
          e.preventDefault();
          var markerAnchor = $(this).attr("href").split("#").pop();
          Drupal.geolocation.executeFeatureOnAllMaps(
            "marker_zoom_to_animate",

            /**
             * @param {GeolocationGoogleMap} map - Current map.
             * @param {MarkerIconSettings} featureSettings - Settings for current feature.
             */
            function (map, featureSettings) {
              $.each(map.mapMarkers, function (index, marker) {
                if (
                  marker.locationWrapper.data("marker-zoom-anchor-id") ===
                  markerAnchor
                ) {
                  $("html, body").animate(
                    {
                      scrollTop: map.wrapper.offset().top,
                    },
                    "slow"
                  );

                  var bounds = new google.maps.LatLngBounds();
                  var loc = new google.maps.LatLng(
                    marker.position.lat(),
                    marker.position.lng()
                  );
                  bounds.extend(loc);

                  map.googleMap.fitBounds(bounds);
                  map.googleMap.panToBounds(bounds);

                  marker.setAnimation(google.maps.Animation.BOUNCE);
                  window.setTimeout(function () {
                    marker.setAnimation(null);
                  }, 2000);
                }
              });

              return false;
            },
            drupalSettings
          );
        });
    },
    detach: function (context, drupalSettings) {},
  };
})(jQuery, Drupal);
