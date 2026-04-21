/**
 * @file
 * Javascript for the Google Geocoding API geocoder.
 */

/**
 * @property {String} drupalSettings.geolocation.geocoder.google_geocoding_api.autocompleteMinLength
 * @property {Object} drupalSettings.geolocation.geocoder.google_geocoding_api.componentRestrictions
 * @property {Object} drupalSettings.geolocation.geocoder.google_geocoding_api.bounds
 * @property {String[]} drupalSettings.geolocation.geocoder.google_geocoding_api.inputIds
 */

(function ($, Drupal) {
  "use strict";

  if (typeof Drupal.geolocation.geocoder === "undefined") {
    return false;
  }

  if (typeof drupalSettings.geolocation === "undefined") {
    return false;
  }

  drupalSettings.geolocation.geocoder.google_geocoding_api =
    drupalSettings.geolocation.geocoder.google_geocoding_api || {};

  Drupal.geolocation.geocoder.googleGeocodingAPI = {};

  var minLength = 1;
  if (
    typeof drupalSettings.geolocation.geocoder.google_geocoding_api
      .autocompleteMinLength !== "undefined" &&
    parseInt(
      drupalSettings.geolocation.geocoder.google_geocoding_api
        .autocompleteMinLength
    )
  ) {
    minLength = parseInt(
      drupalSettings.geolocation.geocoder.google_geocoding_api
        .autocompleteMinLength
    );
  }

  Drupal.geolocation.geocoder.googleGeocodingAPI.attach = function (
    geocoderInput
  ) {
    $(once('geocoder-input', geocoderInput))
      .autocomplete({
        autoFocus: true,
        minLength: minLength,
        source: function (request, response) {
          if (
            typeof Drupal.geolocation.geocoder.googleGeocodingAPI.geocoder ===
            "undefined"
          ) {
            Drupal.geolocation.geocoder.googleGeocodingAPI.geocoder =
              new google.maps.Geocoder();
          }

          var autocompleteResults = [];

          var parameters = {
            address: request.term,
          };
          if (
            typeof drupalSettings.geolocation.geocoder.google_geocoding_api
              .componentRestrictions !== "undefined"
          ) {
            if (
              drupalSettings.geolocation.geocoder.google_geocoding_api
                .componentRestrictions
            ) {
              parameters.componentRestrictions =
                drupalSettings.geolocation.geocoder.google_geocoding_api.componentRestrictions;
            }
          }
          if (
            typeof drupalSettings.geolocation.geocoder.google_geocoding_api
              .bounds !== "undefined"
          ) {
            if (
              drupalSettings.geolocation.geocoder.google_geocoding_api.bounds
            ) {
              parameters.bounds =
                drupalSettings.geolocation.geocoder.google_geocoding_api.bounds;
            }
          }

          Drupal.geolocation.geocoder.googleGeocodingAPI.geocoder.geocode(
            parameters,
            function (results, status) {
              if (status === google.maps.GeocoderStatus.OK) {
                $.each(results, function (index, result) {
                  autocompleteResults.push({
                    value: result.formatted_address,
                    address: result,
                  });
                });
              }
              response(autocompleteResults);
            }
          );
        },

        /**
         * Option form autocomplete selected.
         *
         * @param {Object} event - See jquery doc
         * @param {Object} ui - See jquery doc
         * @param {Object} ui.item - See jquery doc
         */
        select: function (event, ui) {
          if (typeof ui.item.address.geometry.viewport !== "undefined") {
            ui.item.address.geometry.bounds = ui.item.address.geometry.viewport;
          }
          Drupal.geolocation.geocoder.resultCallback(
            ui.item.address,
            $(event.target).data("source-identifier").toString()
          );
        },
      })
      .on("input", function () {
        Drupal.geolocation.geocoder.clearCallback(
          $(this).data("source-identifier").toString()
        );
      });
  };

  /**
   * Attach geocoder input for Google Geocoding API.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches views geocoder input for Google Geocoding API to relevant elements.
   */
  Drupal.behaviors.geolocationGeocoderGoogleGeocodingApi = {
    attach: function (context) {
      Drupal.geolocation.google.addLoadedCallback(function () {
        $.each(
          drupalSettings.geolocation.geocoder.google_geocoding_api.inputIds,
          function (index, inputId) {
            var geocoderInput = $(
              'input.geolocation-geocoder-address[data-source-identifier="' +
                inputId +
                '"]',
              context
            );
            if (geocoderInput.length === 0) {
              return;
            }

            if (geocoderInput.hasClass("geocoder-attached")) {
              return;
            } else {
              geocoderInput.addClass("geocoder-attached");
            }

            if (geocoderInput) {
              Drupal.geolocation.geocoder.googleGeocodingAPI.attach(
                geocoderInput
              );
            }
          }
        );
      });

      // Load Google Maps API and execute all callbacks.
      Drupal.geolocation.google.load();
    },
    detach: function () {},
  };
})(jQuery, Drupal);
