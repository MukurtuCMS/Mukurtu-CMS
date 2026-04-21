/**
 * @file
 * Javascript for the Geolocation map formatter.
 */

(function ($, Drupal) {
  "use strict";

  /**
   * Find and display all maps.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches Geolocation Maps formatter functionality to relevant elements.
   */
  Drupal.behaviors.geolocationMap = {
    /**
     * @param context
     * @param drupalSettings
     * @param {Object} drupalSettings.geolocation
     */
    attach: function (context, drupalSettings) {
      $(once("geolocation-map-processed", ".geolocation-map-wrapper"))
        .each(function (index, item) {
          var mapWrapper = $(item);
          var mapSettings = {};
          var reset = false;
          mapSettings.id = mapWrapper.attr("id");
          mapSettings.wrapper = mapWrapper;

          if (mapWrapper.length === 0) {
            return;
          }

          mapSettings.lat = 0;
          mapSettings.lng = 0;

          if (mapWrapper.data("centre-lat") && mapWrapper.data("centre-lng")) {
            mapSettings.lat = Number(mapWrapper.data("centre-lat"));
            mapSettings.lng = Number(mapWrapper.data("centre-lng"));
          }

          if (mapWrapper.data("map-type")) {
            mapSettings.type = mapWrapper.data("map-type");
          }

          if (typeof drupalSettings.geolocation === "undefined") {
            console.error("Bailing out for lack of settings."); // eslint-disable-line no-console .
            return;
          }

          $.each(
            drupalSettings.geolocation.maps,
            function (mapId, currentSettings) {
              if (mapId === mapSettings.id) {
                mapSettings = $.extend(currentSettings, mapSettings);
              }
            }
          );

          if (mapWrapper.parent().hasClass("preview-section")) {
            if (mapWrapper.parentsUntil("#views-live-preview").length) {
              reset = true;
            }
          }

          var map = Drupal.geolocation.Factory(mapSettings, reset);

          if (!map) {
            once.removeOnce("geolocation-map-processed", mapWrapper);
            return;
          }

          map.addInitializedCallback(function (map) {
            map.removeControls();
            $(".geolocation-map-controls > *", map.wrapper).each(function (
              index,
              control
            ) {
              map.addControl(control);
            });

            map.removeMapMarkers();
            var locations = map.loadMarkersFromContainer();
            $.each(locations, function (index, location) {
              map.setMapMarker(location);
            });

            map.removeShapes();
            var shapes = map.loadShapesFromContainer();
            $.each(shapes, function (index, shape) {
              map.addShape(shape);
            });

            map.setCenter();

            map.wrapper.find(".geolocation-location").hide();
          });

          map.addUpdatedCallback(function (map, mapSettings) {
            map.settings = $.extend(map.settings, mapSettings.settings);
            map.wrapper = mapSettings.wrapper;
            mapSettings.wrapper
              .find(".geolocation-map-container")
              .replaceWith(map.container);
            map.lat = mapSettings.lat;
            map.lng = mapSettings.lng;
            if (typeof mapSettings.map_center !== "undefined") {
              map.mapCenter = mapSettings.map_center;
            }
          });
        });
    },
    detach: function (context, drupalSettings) {},
  };
})(jQuery, Drupal);
