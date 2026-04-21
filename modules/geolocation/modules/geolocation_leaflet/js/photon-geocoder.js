/**
 * @file
 * Javascript for the Photon geocoder.
 */

/**
 * @typedef {Object} PhotonResult
 * @property {Object} properties
 * @property {String} properties.street
 * @property {String} properties.city
 * @property {String} properties.state
 * @property {String} properties.postcode
 * @property {String} properties.country
 * @property {String} properties.housenumber
 */

/**
 * @property {String[]} drupalSettings.geolocation.geocoder.photon.inputIds
 * @property {String} drupalSettings.geolocation.geocoder.photon.locationPriority
 * @property {float} drupalSettings.geolocation.geocoder.photon.locationPriority.lat
 * @property {float} drupalSettings.geolocation.geocoder.photon.locationPriority.lon
 * @property {Boolean} drupalSettings.geolocation.geocoder.photon.removeDuplicates
 */

(function ($, Drupal) {
  'use strict';

  if (typeof Drupal.geolocation.geocoder === 'undefined') {
    return false;
  }

  drupalSettings.geolocation.geocoder.photon = drupalSettings.geolocation.geocoder.photon || {};

  /**
   * Attach geocoder input for Photon.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches views geocoder input for Photon to relevant elements.
   */
  Drupal.behaviors.geolocationGeocoderPhoton = {
    attach: function (context) {
      $.each(drupalSettings.geolocation.geocoder.photon.inputIds, function (index, inputId) {
        var geocoderInput = $('input.geolocation-geocoder-address[data-source-identifier="' + inputId + '"]', context);

        if (geocoderInput.length === 0) {
          return;
        }

        if (geocoderInput.hasClass('geocoder-attached')) {
          return;
        }
        else {
          geocoderInput.addClass('geocoder-attached');
        }

        var minLength = 1;
        if (
            typeof drupalSettings.geolocation.geocoder.photon.autocompleteMinLength !== 'undefined'
            && parseInt(drupalSettings.geolocation.geocoder.photon.autocompleteMinLength)
        ) {
          minLength = parseInt(drupalSettings.geolocation.geocoder.photon.autocompleteMinLength);
        }

        geocoderInput.autocomplete({
          autoFocus: true,
          minLength: minLength,
          source: function (request, response) {
            var autocompleteResults = [];

            var options = {
              q: request.term,
              limit: 3
            };

            var lang = $('html').attr('lang');
            if ($.inArray(lang, ['de', 'en', 'fr']) !== -1) {
              options.lang = lang;
            }

            if (typeof drupalSettings.geolocation.geocoder.photon.locationPriority !== 'undefined') {
              if (
                drupalSettings.geolocation.geocoder.photon.locationPriority.lat
                && drupalSettings.geolocation.geocoder.photon.locationPriority.lon
              ) {
                $.extend(options, drupalSettings.geolocation.geocoder.photon.locationPriority);
              }
            }

            $.getJSON(
                'https://photon.komoot.io/api/',
                options,
                function (data) {
                  if (typeof data.features === 'undefined') {
                    response();
                    return;
                  }
                  /**
                   * @param {int} index
                   * @param {PhotonResult} result
                   */
                  $.each(data.features, function (index, result) {
                    var formatted_address = [];
                    if (typeof result.properties.street !== 'undefined') {
                      var street = result.properties.street;
                      if (typeof result.properties.housenumber !== 'undefined') {
                        street = result.properties.housenumber + ' ' + street;
                      }
                      formatted_address.push(street);
                    }
                    if (typeof result.properties.city !== 'undefined') {
                      formatted_address.push(result.properties.city);
                    }
                    if (typeof result.properties.state !== 'undefined') {
                      formatted_address.push(result.properties.state);
                    }
                    if (typeof result.properties.postcode !== 'undefined') {
                      formatted_address.push(result.properties.postcode);
                    }
                    if (typeof result.properties.country !== 'undefined') {
                      formatted_address.push(result.properties.country);
                    }

                    var formatted_value = '';
                    if (typeof result.properties.name !== 'undefined') {
                      formatted_value = result.properties.name + ' - ';
                    }
                    formatted_value += formatted_address.join(', ');

                    if (drupalSettings.geolocation.geocoder.photon.removeDuplicates) {
                      var existingResults = $.grep(autocompleteResults, function (resultItem) {
                        return resultItem.value === formatted_value;
                      });

                      if (existingResults.length === 0) {
                        autocompleteResults.push({
                          value: formatted_value,
                          result: result
                        });
                      }
                    }
                    else {
                      autocompleteResults.push({
                        value: formatted_value,
                        result: result
                      });
                    }
                  });
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
            if (typeof ui.item.result.geometry.coordinates === 'undefined') {
              return;
            }

            var result = {
              geometry: {
                location: {
                  lat: function () {
                    return ui.item.result.geometry.coordinates[1];
                  },
                  lng: function () {
                    return ui.item.result.geometry.coordinates[0];
                  }
                },
                bounds: ui.item.result.properties.extent
              }
            };

            /** @var ui.item.result.properties.extent array */
            if (typeof ui.item.result.properties.extent !== 'undefined') {
              result.geometry.bounds = {
                north: ui.item.result.properties.extent[1],
                east: ui.item.result.properties.extent[2],
                south: ui.item.result.properties.extent[3],
                west: ui.item.result.properties.extent[0]
              };
            }

            Drupal.geolocation.geocoder.resultCallback(result, $(event.target).data('source-identifier').toString());
          }
        })
        .on('input', function () {
          Drupal.geolocation.geocoder.clearCallback($(this).data('source-identifier').toString());
        });

      });
    },
    detach: function () {}
  };

})(jQuery, Drupal);
