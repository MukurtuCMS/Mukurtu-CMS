/**
 * @file
 * Control Geocoder.
 */

(function (Drupal) {

  'use strict';

  /**
   * Geocoder control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map geocoder functionality to relevant elements.
   */
  Drupal.behaviors.leafletControlGeocoder = {
    attach: function (context, drupalSettings) {

      Drupal.geolocation.executeFeatureOnAllMaps(
        'leaflet_control_geocoder',

          /**
           * @param {GeolocationMapInterface} map
           * @param {GeolocationMapFeatureSettings} featureSettings
           */
        function (map, featureSettings) {
          Drupal.geolocation.geocoder.addResultCallback(
            /**
             * @param {Object} address.geometry.bounds
             */
            function (address) {
              if (typeof address.geometry.bounds !== 'undefined') {
                map.fitBoundaries(address.geometry.bounds, 'leaflet_control_geocoder');
              }
              else {
                var accuracy = undefined;
                if (typeof address.geometry.accuracy === 'undefined') {
                  accuracy = 10000;
                }
                map.setCenterByCoordinates({lat: address.geometry.location.lat(), lng: address.geometry.location.lng()}, accuracy, 'leaflet_control_geocoder');
              }
            },
            map.id
          );

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(Drupal);
