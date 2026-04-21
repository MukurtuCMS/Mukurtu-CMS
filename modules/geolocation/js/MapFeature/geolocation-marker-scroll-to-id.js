/**
 * @file
 * Marker Scroll to Result.
 */

(function ($, Drupal) {
  "use strict";

  /**
   * Recenter control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationMarkerScrollToId = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "geolocation_marker_scroll_to_id",

        /**
         * @param {GeolocationMapInterface} map
         * @param {GeolocationMapFeatureSettings} featureSettings
         */
        function (map, featureSettings) {
          map.addMarkerAddedCallback(function (marker) {
            marker.addEventListener("click", function () {
              var id = marker.locationWrapper.data("scroll-target-id").replace(/\s/g, "");

              var target = $("#" + id + ":visible").first();

              if (target.length === 1) {
                $("html, body").animate(
                  {
                    scrollTop: target.offset().top,
                  },
                  "slow"
                );
              }
            });
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {},
  };
})(jQuery, Drupal);
