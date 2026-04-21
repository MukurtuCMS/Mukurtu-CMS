/**
 * @file
 * Common Map Leaflet.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Dynamic map handling aka "AirBnB mode".
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationCommonMapLeaflet = {
    /**
     * @param {GeolocationSettings} drupalSettings.geolocation
     */
    attach: function (context, drupalSettings) {
      $.each(
        drupalSettings.geolocation.commonMap,

        /**
         * @param {String} mapId - ID of current map
         * @param {CommonMapSettings} commonMapSettings - settings for current map
         */
        function (mapId, commonMapSettings) {
          if (
            typeof commonMapSettings.dynamic_map !== 'undefined'
            && commonMapSettings.dynamic_map.enable
          ) {
            var map = Drupal.geolocation.getMapById(mapId);

            if (!map) {
              return;
            }

            if (map.container.hasClass('geolocation-common-map-leaflet-processed')) {
              return;
            }
            map.container.addClass('geolocation-common-map-leaflet-processed');

            /**
             * Update the view depending on dynamic map settings and capability.
             *
             * One of several states might occur now. Possible state depends on whether:
             * - view using AJAX is enabled
             * - map view is the containing (page) view or an attachment
             * - the exposed form is present and contains the boundary filter
             * - map settings are consistent
             *
             * Given these factors, map boundary changes can be handled in one of three ways:
             * - trigger the views AJAX "RefreshView" command
             * - trigger the exposed form causing a regular POST reload
             * - fully reload the website
             *
             * These possibilities are ordered by UX preference.
             */
            if (
              map.container.length
              && map.type === 'leaflet'
            ) {
              map.addPopulatedCallback(function (map) {
                var geolocationMapIdleTimer;
                map.leafletMap.on('moveend zoomend', /** @param {LeafletMouseEvent} e */function (e) {
                  clearTimeout(geolocationMapIdleTimer);

                  geolocationMapIdleTimer = setTimeout(
                    function () {
                      var ajaxSettings = Drupal.geolocation.commonMap.dynamicMapViewsAjaxSettings(commonMapSettings);

                      // Add bounds.
                      var currentBounds = map.leafletMap.getBounds();
                      var bound_parameters = {};
                      bound_parameters[commonMapSettings['dynamic_map']['parameter_identifier'] + '[lat_north_east]'] = currentBounds.getNorthEast().lat;
                      bound_parameters[commonMapSettings['dynamic_map']['parameter_identifier'] + '[lng_north_east]'] = currentBounds.getNorthEast().lng;
                      bound_parameters[commonMapSettings['dynamic_map']['parameter_identifier'] + '[lat_south_west]'] = currentBounds.getSouthWest().lat;
                      bound_parameters[commonMapSettings['dynamic_map']['parameter_identifier'] + '[lng_south_west]'] = currentBounds.getSouthWest().lng;

                      ajaxSettings.submit = $.extend(
                        ajaxSettings.submit,
                        bound_parameters
                      );

                      Drupal.ajax(ajaxSettings).execute();
                    },
                    commonMapSettings.dynamic_map.views_refresh_delay
                  );
                });
              });
            }
          }
        });
    },
    detach: function (context, drupalSettings) {}
  };

})(jQuery, Drupal);
