/**
 * @file
 * Zoom Control.
 */

(function ($, Drupal) {
  "use strict";

  /**
   * Zoom Control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *  Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.baiduMapsNavigationControls = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "baidu_maps_controls",

        /**
         * @param {GeolocationBaiduMap} map - Current map.
         * @param {GeolocationMapFeatureSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          const opts = {
            type: window[featureSettings.type],
            anchor: window[featureSettings.position],
          };
          map.baiduMap.addControl(new BMap.NavigationControl(opts));
          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {},
  };
})(jQuery, Drupal);
