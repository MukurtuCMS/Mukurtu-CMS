/**
 * @file
 * Restrict map.
 */

/**
 * @typedef {Object} MapRestrictionSettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {String} north
 * @property {String} south
 * @property {String} east
 * @property {String} west
 * @property {Boolean} strict
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Map restriction.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationMapRestriction = {
    attach: function (context, drupalSettings) {

      Drupal.geolocation.executeFeatureOnAllMaps(
          'map_restriction',

          /**
           * @param {GeolocationGoogleMap} map - Current map.
           * @param {MapRestrictionSettings} featureSettings - Settings for current feature.
           */
          function (map, featureSettings) {
            map.addInitializedCallback(function (map) {
              map.googleMap.setOptions({
                restriction: {
                  latLngBounds: {
                    north: parseFloat(featureSettings.north),
                    south: parseFloat(featureSettings.south),
                    east: parseFloat(featureSettings.east),
                    west: parseFloat(featureSettings.west)
                  },
                strictBounds: Boolean(featureSettings.strict)
                }
              });
            });

            return true;
          },
          drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };
})(jQuery, Drupal);
