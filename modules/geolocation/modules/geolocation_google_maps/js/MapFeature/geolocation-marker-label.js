/**
 * @file
 * Marker Icon.
 */

/**
 * @typedef {Object} MarkerLabelSettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {String} color
 * @property {String} fontFamily
 * @property {String} fontSize
 * @property {String} fontWeight
 */

(function (Drupal) {
  "use strict";

  /**
   * Google MarkerIcon.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationMarkerLabel = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "marker_label",

        /**
         * @param {GeolocationGoogleMap} map - Current map.
         * @param {MarkerLabelSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          /**
           * @param {google.maps.Marker} currentMarker
           */
          map.addMarkerAddedCallback(function (currentMarker) {
            var newLabel = {};
            var currentLabel = currentMarker.getLabel();

            if (typeof currentLabel === "undefined") {
              return;
            }

            var text;
            if (typeof currentLabel === "string") {
              text = currentLabel;
            } else {
              text = currentLabel.text;
            }

            if (!text.length) {
              return;
            }

            newLabel.text = text;

            if (featureSettings.color) {
              newLabel.color = featureSettings.color;
            }

            if (featureSettings.fontFamily) {
              newLabel.fontFamily = featureSettings.fontFamily;
            }

            if (featureSettings.fontSize) {
              newLabel.fontSize = featureSettings.fontSize;
            }

            if (featureSettings.fontWeight) {
              newLabel.fontWeight = featureSettings.fontWeight;
            }

            currentMarker.setLabel(newLabel);
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {},
  };
})(Drupal);
