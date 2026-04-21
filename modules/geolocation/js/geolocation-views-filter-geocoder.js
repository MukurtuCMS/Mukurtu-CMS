/**
 * @file
 * Javascript for the Google geocoder function, specifically the views filter.
 */

(function ($, Drupal) {
  "use strict";

  /**
   * Attach common map style functionality.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches views geolocation filter geocoder to relevant elements.
   */
  Drupal.behaviors.geolocationViewsFilterGeocoder = {
    /**
     * @param {Object} context
     * @param {Object} drupalSettings
     * @param {String} drupalSettings.geolocation.geocoder.viewsFilterGeocoder
     */
    attach: function (context, drupalSettings) {
      $.each(
        drupalSettings.geolocation.geocoder.viewsFilterGeocoder,
        function (elementId, settings) {
          /**
           * @param {google.map.GeocoderResult} address - Google address object.
           */
          Drupal.geolocation.geocoder.addResultCallback(function (address) {
            if (typeof address.geometry.location === "undefined") {
              return false;
            }

            if (typeof address.geometry.viewport === "undefined") {
              address.geometry.viewport = {
                getNorthEast: function () {
                  return {
                    lat: function () {
                      return address.geometry.location.lat();
                    },
                    lng: function () {
                      return address.geometry.location.lng();
                    },
                  };
                },
                getSouthWest: function () {
                  return {
                    lat: function () {
                      return address.geometry.location.lat();
                    },
                    lng: function () {
                      return address.geometry.location.lng();
                    },
                  };
                },
              };
            }

            $(context)
              .find("input[name='" + elementId + "[lat_north_east]']")
              .val(address.geometry.viewport.getNorthEast().lat());
            $(context)
              .find("input[name='" + elementId + "[lng_north_east]']")
              .val(address.geometry.viewport.getNorthEast().lng());
            $(context)
              .find("input[name='" + elementId + "[lat_south_west]']")
              .val(address.geometry.viewport.getSouthWest().lat());
            $(context)
              .find("input[name='" + elementId + "[lng_south_west]']")
              .val(address.geometry.viewport.getSouthWest().lng());
          }, elementId.toString());

          Drupal.geolocation.geocoder.addClearCallback(function () {
            $(context)
              .find("input[name='" + elementId + "[lat_north_east]']")
              .val("");
            $(context)
              .find("input[name='" + elementId + "[lng_north_east]']")
              .val("");
            $(context)
              .find("input[name='" + elementId + "[lat_south_west]']")
              .val("");
            $(context)
              .find("input[name='" + elementId + "[lng_south_west]']")
              .val("");
          }, elementId.toString());

          delete drupalSettings.geolocation.geocoder.viewsFilterGeocoder[
            elementId
          ];
        }
      );
    },
  };
})(jQuery, Drupal);
