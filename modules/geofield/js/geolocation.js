// geo-location shim
// Source: https://gist.github.com/paulirish/366184

// Currently only serves lat/long
// depends on jQuery

(function (geolocation, $) {
  if (geolocation) return;

  let cache;

  geolocation = window.navigator.geolocation = {};
  geolocation.getCurrentPosition = function (callback) {
    if (cache) callback(cache);

    $.getScript('//www.google.com/jsapi', function () {
      cache = {
        coords: {
          latitude: google.loader.ClientLocation.latitude,
          longitude: google.loader.ClientLocation.longitude,
        },
      };

      callback(cache);
    });
  };

  geolocation.watchPosition = geolocation.getCurrentPosition;
})(navigator.geolocation, jQuery);

(function ($) {
  Drupal.behaviors.geofieldGeolocation = {
    attach: function (context, settings) {
      let fields = $(context);

      // Callback for getCurrentPosition on geofield widget html5 geocode button
      function updateLocation(position) {
        fields
          .find('.auto-geocode .geofield-lat')
          .val(position.coords.latitude);
        fields
          .find('.auto-geocode .geofield-lon')
          .val(position.coords.longitude);
      }

      // Callback for getCurrentPosition on geofield proximity client position.
      function getClientOrigin(position) {
        const lat = position.coords.latitude.toFixed(6);
        const lon = position.coords.longitude.toFixed(6);
        latitudeInput.val(lat);
        longitudeInput.val(lon);
        latitudeSpan.text(lat);
        longitudeSpan.text(lon);
        return false;
      }

      // don't do anything if we're on field configuration
      if (!$(context).find('#edit-instance').length) {
        let fields = $(context);
        // check that we have something to fill up
        // on multi values check only that the first one is em  pty
        if (
          fields.find('.auto-geocode .geofield-lat').val() === '' &&
          fields.find('.auto-geocode .geofield-lon').val() === ''
        ) {
          // Check to see if we have geolocation support, either natively or through Google.
          if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(updateLocation);
          } else {
            console.log('Geolocation is not supported by this browser.');
          }
        }
      }

      // React on the geofield widget html5 geocode button click.
      once(
        'geofield_geolocation',
        '[name="geofield-html5-geocode-button"]',
      ).forEach(function (e) {
        $(e).click(function (e) {
          e.preventDefault();
          fields = $(this).parents('.auto-geocode').parent();
          if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(updateLocation);
          } else {
            console.log('Geolocation is not supported by this browser.');
          }
        });
      });

      let latitudeInput,
        longitudeInput,
        latitudeSpan,
        longitudeSpan = '';

      // React on the geofield proximity client location source.
      once('geofield_geolocation', '.proximity-origin-client').forEach(
        function (e) {
          latitudeInput = $(e).find('.geofield-lat').first();
          longitudeInput = $(e).find('.geofield-lon').first();
          latitudeSpan = $(e).find('.geofield-lat-summary').first();
          longitudeSpan = $(e).find('.geofield-lon-summary').first();
          if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(getClientOrigin);
          } else {
            console.log('Geolocation is not supported by this browser.');
          }
        },
      );
    },
  };
})(jQuery);
