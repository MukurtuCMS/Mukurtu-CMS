/**
 * @file
 * Javascript for the Geolocation location input.
 */

(function ($, Drupal) {
  "use strict";

  /**
   * Generic behavior.
   *
   * @type {Drupal~behavior}
   * @type {Object} drupalSettings.geolocation
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches functionality to relevant elements.
   */
  Drupal.behaviors.locationInputGeocoder = {
    attach: function (context, drupalSettings) {
      $.each(
        drupalSettings.geolocation.locationInput.geocoder,
        function (index, settings) {
          var inputWrapper =
            $(once("location-input-geocoder-processed", $(
               ".location-input-geocoder." + settings.identifier,
               context
             )))
            .first();
          if (inputWrapper.length) {
            if (settings.hideForm) {
              inputWrapper.hide();
            }

            var latitudeInput = inputWrapper
              .find("input.geolocation-input-latitude")
              .first();
            var longitudeInput = inputWrapper
              .find("input.geolocation-input-longitude")
              .first();
            var geocoderAddressInput = inputWrapper
              .parent()
              .find("input.geolocation-geocoder-address")
              .first();

            Drupal.geolocation.geocoder.addResultCallback(function (address) {
              if (typeof address.geometry.location === "undefined") {
                return false;
              }
              latitudeInput.val(address.geometry.location.lat());
              longitudeInput.val(address.geometry.location.lng());

              if (settings.autoSubmit) {
                if (geocoderAddressInput.length) {
                  geocoderAddressInput.val(address.formatted_address);
                }
                inputWrapper
                  .closest("form")
                  .find("input.js-form-submit")
                  .first()
                  .click();
              }
            }, settings.identifier);

            Drupal.geolocation.geocoder.addClearCallback(function () {
              latitudeInput.val("");
              longitudeInput.val("");
            }, settings.identifier);
          }
        }
      );
    },
  };
})(jQuery, Drupal);
