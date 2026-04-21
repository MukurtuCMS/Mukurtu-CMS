/**
 * @file
 * Control Loading Indicator.
 */

(function ($, Drupal) {

  'use strict';

  var ajaxBeforeSendOriginal = Drupal.Ajax.prototype.beforeSend;

  Drupal.Ajax.prototype.beforeSend = function (xmlhttprequest, options) {
    var loadingIndicator = $(this.selector + ' .geolocation-map-control .loading-indicator');
    if (loadingIndicator.length) {
      loadingIndicator.show();
    }

    ajaxBeforeSendOriginal.call(this, xmlhttprequest, options);
  };

  /**
   * Loading Indicator control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationControlRecenter = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'control_loading_indicator',

        /**
         * @param {GeolocationMapInterface} map
         * @param {GeolocationMapFeatureSettings} featureSettings
         */
        function (map, featureSettings) {
          var loadingIndicator = $('.geolocation-map-control .loading-indicator', map.wrapper);

          map.addPopulatedCallback(function (map) {
            loadingIndicator.hide();
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(jQuery, Drupal);
