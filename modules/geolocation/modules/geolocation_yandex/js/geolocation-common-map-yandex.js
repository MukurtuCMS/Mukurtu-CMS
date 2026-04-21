/**
 * @file
 * Common Map Yandex.
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
  Drupal.behaviors.geolocationCommonMapYandex = {
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

            if (map.container.hasClass('geolocation-common-map-yandex-processed')) {
              return;
            }
            map.container.addClass('geolocation-common-map-yandex-processed');

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
              && map.type === 'yandex'
            ) {
              map.addPopulatedCallback(function (map) {
                var geolocationMapIdleTimer;
                map.yandexMap.events.add('boundschange', function (e) {
                  clearTimeout(geolocationMapIdleTimer);

                  geolocationMapIdleTimer = setTimeout(
                    function () {
                      var ajaxSettings = Drupal.geolocation.commonMap.dynamicMapViewsAjaxSettings(commonMapSettings);

                      // Add bounds.
                      var currentBounds = map.yandexMap.getBounds();
                      var bound_parameters = {};
                      bound_parameters[commonMapSettings['dynamic_map']['parameter_identifier'] + '[lat_north_east]'] = currentBounds[1][1];
                      bound_parameters[commonMapSettings['dynamic_map']['parameter_identifier'] + '[lng_north_east]'] = currentBounds[1][0];
                      bound_parameters[commonMapSettings['dynamic_map']['parameter_identifier'] + '[lat_south_west]'] = currentBounds[0][1];
                      bound_parameters[commonMapSettings['dynamic_map']['parameter_identifier'] + '[lng_south_west]'] = currentBounds[0][0];

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
