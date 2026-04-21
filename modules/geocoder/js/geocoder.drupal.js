/* eslint-disable max-nested-callbacks,func-names */
/**
 * @file
 * Javascript for the Geocoder Origin Autocomplete.
 */

(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.geocode_origin_autocomplete = {
    attach(context, settings) {
      function geocode(address, providers, addressFormat) {
        const { baseUrl } = drupalSettings.path;
        const { pathPrefix } = drupalSettings.path;
        const geocodePath = `${baseUrl + pathPrefix}geocoder/api/geocode`;
        const addressFormatQueryUrl =
          addressFormat === null ? "" : `&address_format=${addressFormat}`;
        return $.ajax({
          url: `${geocodePath}?address=${encodeURIComponent(
            address,
          )}&geocoder=${providers}${addressFormatQueryUrl}`,
          type: "GET",
          contentType: "application/json; charset=utf-8",
          dataType: "json",
        });
      }

      // Run filters on page load if state is saved by browser.
      once(
        "autocomplete-enabled",
        ".origin-address-autocomplete .address-input",
        context,
      ).forEach(function (element) {
        const providers =
          settings.geocode_origin_autocomplete.providers.toString();
        const { address_format: addressFormat } =
          settings.geocode_origin_autocomplete;
        $(element)
          .autocomplete({
            autoFocus: true,
            minLength: settings.geocode_origin_autocomplete.minTerms || 4,
            delay: settings.geocode_origin_autocomplete.delay || 800,
            // This bit uses the geocoder to fetch address values.
            source(request, response) {
              const thisElement = this.element;
              thisElement.addClass("ui-autocomplete-loading");
              // Execute the geocoder.
              $.when(
                geocode(request.term, providers, addressFormat).then(
                  // On Resolve/Success.
                  function (results) {
                    response(
                      $.map(results, function (item) {
                        thisElement.removeClass("ui-autocomplete-loading");
                        return {
                          // the value property is needed to be passed to the select.
                          value: item.formatted_address,
                        };
                      }),
                    );
                  },
                  // On Reject/Error.
                  function () {
                    response(function () {
                      return false;
                    });
                  },
                ),
              );
            },
          })
          .addClass("form-autocomplete");
      });
    },
  };
})(jQuery, Drupal, drupalSettings);
