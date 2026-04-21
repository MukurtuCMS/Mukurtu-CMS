/**
 * Attach functionality for Proximity Origin Summary Lat and Lon behaviours.
 */
(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.geofield_proximity_origin_summary_update = {
    attach: function (context, settings) {
      // Sync the .proximity-origin-summary lat and lon elements according to
      // geofield-lat and geofield-lon changes.
      once(
        'geofield_proximity_origin_summary_update',
        '.proximity-origin',
      ).forEach(function (e) {
        const self = e;
        $('.geofield-lat', e).on('change', function(e) {
          $('.proximity-origin-summary .geofield-lat', self).text($(this).val())
        });
        $('.geofield-lon', e).on('change', function(e) {
          $('.proximity-origin-summary .geofield-lon', self).text($(this).val())
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
