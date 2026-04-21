/**
 * @file
 * Marker Icon.
 */

/**
 * @typedef {Object} MarkerIconSettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {String} markerIconPath
 * @property {Array} anchor
 * @property {Number} anchor.x
 * @property {Number} anchor.y
 * @property {Array} labelOrigin
 * @property {Number} labelOrigin.x
 * @property {Number} labelOrigin.y
 * @property {Array} origin
 * @property {Number} origin.x
 * @property {Number} origin.y
 * @property {Array} size
 * @property {Number} size.width
 * @property {Number} size.height
 * @property {Array} scaledSize
 * @property {Number} scaledSize.width
 * @property {Number} scaledSize.height
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
  Drupal.behaviors.geolocationMarkerIcon = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "marker_icon",

        /**
         * @param {GeolocationGoogleMap} map - Current map.
         * @param {MarkerIconSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          map.addMarkerAddedCallback(function (currentMarker) {
            var newIcon = {};

            var currentIcon = currentMarker.getIcon();
            if (typeof currentIcon === "undefined") {
              if (typeof featureSettings.markerIconPath === "string") {
                newIcon.url = featureSettings.markerIconPath;
              } else {
                return;
              }
            } else if (typeof currentIcon === "string") {
              newIcon.url = currentIcon;
            } else if (typeof currentIcon.url === "string") {
              newIcon.url = currentIcon.url;
            }

            var anchorX =
              currentMarker.locationWrapper.data("marker-icon-anchor-x") ||
              featureSettings.anchor.x;
            var anchorY =
              currentMarker.locationWrapper.data("marker-icon-anchor-y") ||
              featureSettings.anchor.y;
            var labelOriginX =
              currentMarker.locationWrapper.data(
                "marker-icon-label-origin-x"
              ) || featureSettings.labelOrigin.x;
            var labelOriginY =
              currentMarker.locationWrapper.data(
                "marker-icon-label-origin-y"
              ) || featureSettings.labelOrigin.y;
            var originX =
              currentMarker.locationWrapper.data("marker-icon-origin-x") ||
              featureSettings.origin.x;
            var originY =
              currentMarker.locationWrapper.data("marker-icon-origin-y") ||
              featureSettings.origin.y;
            var sizeWidth =
              currentMarker.locationWrapper.data("marker-icon-size-width") ||
              featureSettings.size.width;
            var sizeHeight =
              currentMarker.locationWrapper.data("marker-icon-size-height") ||
              featureSettings.size.height;
            var scaledSizeWidth =
              currentMarker.locationWrapper.data(
                "marker-icon-scaled-size-width"
              ) || featureSettings.scaledSize.width;
            var scaledSizeHeight =
              currentMarker.locationWrapper.data(
                "marker-icon-scaled-size-height"
              ) || featureSettings.scaledSize.height;

            if (anchorX !== null && anchorY !== null) {
              newIcon.anchor = new google.maps.Point(anchorX, anchorY);
            }

            if (labelOriginX !== null && labelOriginY !== null) {
              newIcon.labelOrigin = new google.maps.Point(
                labelOriginX,
                labelOriginY
              );
            }

            if (originX !== null && originY !== null) {
              newIcon.origin = new google.maps.Point(originX, originY);
            }

            if (sizeWidth !== null && sizeHeight !== null) {
              newIcon.size = new google.maps.Size(sizeWidth, sizeHeight);
            }

            if (scaledSizeWidth !== null && scaledSizeHeight !== null) {
              newIcon.scaledSize = new google.maps.Size(
                scaledSizeWidth,
                scaledSizeHeight
              );
            }

            currentMarker.setIcon(newIcon);
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {},
  };
})(Drupal);
