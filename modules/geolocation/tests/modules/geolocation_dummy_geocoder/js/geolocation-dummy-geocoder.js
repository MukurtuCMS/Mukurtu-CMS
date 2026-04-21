/**
 * @file
 * Javascript for the Dummy geocoder.
 */

(function ($, Drupal) {
  'use strict';

  if (typeof Drupal.geolocation.geocoder === 'undefined') {
    return false;
  }

  /**
   * Attach geocoder input for Dummy.
   */
  Drupal.behaviors.geolocationGeocoderDummy = {
    attach: function (context) {
      $(once('geolocation-geocoder-dummy', 'input.geolocation-geocoder-dummy', context)).on('input', function () {
        var that = $(this);
        Drupal.geolocation.geocoder.clearCallback(that.data('source-identifier'));

        if (!that.val().length) {
          return;
        }

        $.ajax(Drupal.url('geolocation_dummy_geocoder/geocode/' + that.val())).done(function (data) {
          if (data.length < 3) {
            return;
          }
          var address = {
            geometry: {
              location: {
                lat: function () {
                  return data.location.lat;
                },
                lng: function () {
                  return data.location.lng;
                }
              }
            }
          };
          Drupal.geolocation.geocoder.resultCallback(address, that.data('source-identifier'));
        });
      });
    }
  };

})(jQuery, Drupal);
