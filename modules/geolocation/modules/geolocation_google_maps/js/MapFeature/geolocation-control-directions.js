/**
 * @file
 * Directions.
 */

/**
 * @typedef {Object} MapFeatureDirectionsSettings
 *
 * @extends {GeolocationMapFeatureSettings}

 * @property {object} settings
 * @property {string} settings.directions_container_custom_id
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Directions.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationGoogleMapsDirections = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'geolocation_google_maps_control_directions',

        /**
         * @param {GeolocationGoogleMap} map - Current map.
         * @param {MapFeatureDirectionsSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          var form = map.wrapper.find('.geolocation-google-maps-directions-controls');
          if (form.length !== 1) {
            return;
          }

          var originElement = form.find('[name=geolocation-google-maps-directions-controls-origin]');
          var destinationElement = form.find('[name=geolocation-google-maps-directions-controls-destination]');
          var travelModeElement = form.find('[name=geolocation-google-maps-directions-controls-travel-mode]');

          var directionsContainer;
          if (featureSettings.settings.directions_container_custom_id) {
            directionsContainer = $('#' + featureSettings.settings.directions_container_custom_id);
          }
          else {
            directionsContainer = map.wrapper.siblings('.geolocation-google-maps-directions-container');
          }

          var directionsService = new google.maps.DirectionsService();
          var directionsRenderer = new google.maps.DirectionsRenderer();

          directionsRenderer.setMap(map.googleMap);
          directionsRenderer.setPanel(directionsContainer.get(0));

          form.submit(function (event) {
            event.preventDefault();

            var travelMode;
            switch (travelModeElement.val()) {
              case 'bicycling':
                travelMode = google.maps.TravelMode.BICYCLING;
                break;

              case 'transit':
                travelMode = google.maps.TravelMode.TRANSIT;
                break;

              case 'walking':
                travelMode = google.maps.TravelMode.WALKING;
                break;

              case 'driving':
              default:
                travelMode = google.maps.TravelMode.DRIVING;
            }

            directionsService.route(
              {
                origin: originElement.val(),
                destination: destinationElement.val(),
                travelMode: travelMode
              },
              function (result, status) {
                switch (status) {
                  case google.maps.DirectionsStatus.OK:
                    directionsRenderer.setDirections(result);
                    break;

                  case google.maps.DirectionsStatus.NOT_FOUND:
                    directionsContainer.text('Could not identify the address entered.');
                    break;

                  case google.maps.DirectionsStatus.ZERO_RESULTS:
                    directionsContainer.text('No routes found.');
                    break;

                  case google.maps.DirectionsStatus.REQUEST_DENIED:
                    directionsContainer.text('Request denied. Directions API not enabled?');
                    break;

                  case google.maps.DirectionsStatus.UNKNOWN_ERROR:
                    directionsContainer.text('Unknown error.');
                    break;

                  case google.maps.DirectionsStatus.OVER_QUERY_LIMIT:
                    directionsContainer.text('Over query limit.');
                    break;
                }
              }
            );
          });

          return true;
        },
        drupalSettings
      );
    }
  };
})(jQuery, Drupal);
