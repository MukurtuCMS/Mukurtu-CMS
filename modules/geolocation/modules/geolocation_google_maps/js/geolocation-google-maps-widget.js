/**
 * @file
 * Javascript for the GoogleMaps Geolocation map widget.
 */

(function ($, Drupal) {
  "use strict";

  /**
   * GoogleMaps widget.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches Geolocation Maps widget functionality to relevant elements.
   */
  Drupal.behaviors.geolocationGoogleMapsWidget = {
    attach: function (context, drupalSettings) {
      $(once("geolocation-google-maps-widget-processed", ".geolocation-map-widget", context))
        .each(function (index, item) {
          var widgetId = $(item).attr("id").toString();
          var widget = Drupal.geolocation.widget.getWidgetById(widgetId);
          if (!widget) {
            return;
          }

          widget.map.addCenterUpdatedCallback(function (
            location,
            accuracy,
            identifier
          ) {
            if (typeof identifier === "undefined") {
              return;
            }

            if (
              identifier === "google_control_locate" ||
              identifier === "google_control_geocoder"
            ) {
              widget.locationAlteredCallback(
                "widget-map-moved",
                location,
                null
              );
            }
          });
        });
    },
    detach: function (context, drupalSettings) {},
  };
})(jQuery, Drupal);
