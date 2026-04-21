/**
 * @file
 * Javascript for the Google Places API geocoder.
 */

/**
 * @property {Object} drupalSettings.geolocation.geocoder.google_places_api.componentRestrictions
 */

(function ($, Drupal) {
  'use strict';

  /* global google */

  if (typeof Drupal.geolocation.geocoder === 'undefined') {
    return false;
  }

  Drupal.geolocation.geocoder.googlePlacesAPI = {};
  drupalSettings.geolocation.geocoder.google_places_api = drupalSettings.geolocation.geocoder.google_places_api || {};

  var minLength = 1;
  if (
      typeof drupalSettings.geolocation.geocoder.google_places_api.autocompleteMinLength !== 'undefined'
      && parseInt(drupalSettings.geolocation.geocoder.google_places_api.autocompleteMinLength)
  ) {
    minLength = parseInt(drupalSettings.geolocation.geocoder.google_places_api.autocompleteMinLength);
  }

  /**
   * @param {HTMLElement} context Context
   */
  Drupal.geolocation.geocoder.googlePlacesAPI.attach = function (context) {
    var autocomplete = $(once('geolocation-geocoder-autocomplete', 'input.geolocation-geocoder-address', context));
    if (!autocomplete.length) {
      return;
    }

    autocomplete.autocomplete({
      autoFocus: true,
      minLength: minLength,
      source: function (request, response) {
        var autocompleteResults = [];
        var componentRestrictions = {};
        if (typeof drupalSettings.geolocation.geocoder.google_places_api.componentRestrictions !== 'undefined') {
          componentRestrictions = drupalSettings.geolocation.geocoder.google_places_api.componentRestrictions;
          if (componentRestrictions.country !== undefined && !$.isArray(componentRestrictions.country)) {
            componentRestrictions.country = componentRestrictions.country.split(',');
          }
        }

        Drupal.geolocation.geocoder.googlePlacesAPI.autocompleteService.getPlacePredictions(
          {
            input: request.term,
            componentRestrictions: componentRestrictions,
            sessionToken: Drupal.geolocation.geocoder.googlePlacesAPI.sessionToken
          },

          function (results, status) {
            if (status === google.maps.places.PlacesServiceStatus.OK) {
              $.each(results, function (index, result) {
                autocompleteResults.push({
                  value: result.description,
                  place_id: result.place_id,
                  classes: result.types.reverse()
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
        Drupal.geolocation.geocoder.googlePlacesAPI.service.getDetails(
          {
            placeId: ui.item.place_id
          },

          function (place, status) {
            if (status === google.maps.places.PlacesServiceStatus.OK) {
              if (typeof place.geometry.location === 'undefined') {
                return;
              }
              Drupal.geolocation.geocoder.resultCallback(place, $(event.target).data('source-identifier').toString());
            }
          }
        );
      }
    })
    .autocomplete('instance')
    ._renderItem = function (ul, item) {
      return $('<li></li>')
        .attr('data-value', item.value)
        .append('<div><div class="geolocation-geocoder-item ' + item.classes.join(' ') + '">' + item.label + '</div></div>')
        .appendTo(ul);
    };

    autocomplete.on('input', function () {
      Drupal.geolocation.geocoder.clearCallback($(this).data('source-identifier').toString());
    });
  };

  /**
   * Attach geocoder input for Google places API.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches views geocoder input for Google places API to relevant elements.
   */
  Drupal.behaviors.geolocationGeocoderGooglePlacesApi = {
    attach: function (context) {
      var attribution_block = $('#geolocation-google-places-api-attribution');
      if (attribution_block.length === 0) {
        console.error("Geolocation Google Places API attribution block missing."); // eslint-disable-line no-console .
        return;
      }

      Drupal.geolocation.google.addLoadedCallback(function () {
        if (typeof Drupal.geolocation.geocoder.googlePlacesAPI.service === 'undefined') {
          Drupal.geolocation.geocoder.googlePlacesAPI.service = new google.maps.places.PlacesService(attribution_block[0]);
          // Create a new session token.
          Drupal.geolocation.geocoder.googlePlacesAPI.sessionToken = new google.maps.places.AutocompleteSessionToken();
          Drupal.geolocation.geocoder.googlePlacesAPI.autocompleteService = new google.maps.places.AutocompleteService();
        }

        Drupal.geolocation.geocoder.googlePlacesAPI.attach(context);
      });

      // Load Google Maps API and execute all callbacks.
      Drupal.geolocation.google.load();
    }
  };

})(jQuery, Drupal);
