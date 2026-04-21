/**
 * @file
 * Spiderfying.
 */

/**
 * @typedef {Object} OverlappingMarkerSpiderfierInterface
 *
 * @property {function} addMarker
 * @property {string} markerStatus.SPIDERFIED
 * @property {string} markerStatus.UNSPIDERFIED
 * @property {string} markerStatus.SPIDERFIABLE
 * @property {string} markerStatus.UNSPIDERFIABLE
 */

/**
 * @typedef {Object} SpiderfyingSettings
 *
 * @extends {GeolocationMapFeatureSettings}
 *
 * @property {String} spiderfiable_marker_path
 * @property {String} markersWontMove
 * @property {String} markersWontHide
 * @property {String} keepSpiderfied
 * @property {String} ignoreMapClick
 * @property {String} nearbyDistance
 * @property {String} circleSpiralSwitchover
 * @property {String} circleFootSeparation
 * @property {String} spiralFootSeparation
 * @property {String} spiralLengthStart
 * @property {String} spiralLengthFactor
 * @property {String} legWeight
 */

(function ($, Drupal) {
  "use strict";

  /**
   * Spiderfying.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationSpiderfying = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        "spiderfying",

        /**
         * @param {GeolocationGoogleMap} map - Current map.
         * @param {SpiderfyingSettings} featureSettings - Settings for current feature.
         */
        function (map, featureSettings) {
          if (typeof OverlappingMarkerSpiderfier === "undefined") {
            return;
          }

          /* global OverlappingMarkerSpiderfier */

          map.addInitializedCallback(function (map) {
            var oms = null;

            /**
             * @type {OverlappingMarkerSpiderfierInterface} OverlappingMarkerSpiderfier
             */
            oms = new OverlappingMarkerSpiderfier(map.googleMap, {
              markersWontMove: featureSettings.markersWontMove,
              markersWontHide: featureSettings.markersWontHide,
              keepSpiderfied: featureSettings.keepSpiderfied,
              ignoreMapClick: featureSettings.ignoreMapClick,
            });

            if (featureSettings.nearbyDistance) {
              oms.nearbyDistance = featureSettings.nearbyDistance;
            }

            if (featureSettings.circleSpiralSwitchover) {
              oms.circleSpiralSwitchover =
                featureSettings.circleSpiralSwitchover;
            }

            if (featureSettings.circleFootSeparation) {
              oms.circleFootSeparation = featureSettings.circleFootSeparation;
            }

            if (featureSettings.spiralFootSeparation) {
              oms.spiralFootSeparation = featureSettings.spiralFootSeparation;
            }

            if (featureSettings.spiralLengthStart) {
              oms.spiralLengthStart = featureSettings.spiralLengthStart;
            }

            if (featureSettings.spiralLengthFactor) {
              oms.spiralLengthFactor = featureSettings.spiralLengthFactor;
            }

            if (featureSettings.legWeight) {
              oms.legWeight = featureSettings.legWeight;
            }

            if (oms) {
              var geolocationOmsMarkerFunction = function (marker) {
                google.maps.event.addListener(
                  marker,
                  "spider_format",
                  function (status) {
                    /**
                     * @param {Object} marker.originalIcon
                     */
                    if (typeof marker.originalIcon === "undefined") {
                      var originalIcon = marker.getIcon();

                      if (typeof originalIcon === "undefined") {
                        marker.orginalIcon = "";
                      } else if (
                        typeof originalIcon !== "undefined" &&
                        originalIcon !== null &&
                        typeof originalIcon.url !== "undefined" &&
                        originalIcon.url ===
                          featureSettings.spiderfiable_marker_path
                      ) {
                        // Do nothing.
                      } else {
                        marker.orginalIcon = originalIcon;
                      }
                    }

                    var icon = null;
                    if (featureSettings.spiralIconWidth && featureSettings.spiralIconHeight) {
                      var iconSize = new google.maps.Size(featureSettings.spiralIconWidth, featureSettings.spiralIconHeight);
                    } else {
                      var iconSize = new google.maps.Size(26, 37);
                    }
                    switch (status) {
                      case OverlappingMarkerSpiderfier.markerStatus
                        .SPIDERFIABLE:
                        icon = {
                          url: featureSettings.spiderfiable_marker_path,
                          size: iconSize,
                          scaledSize: iconSize,
                        };
                        break;

                      case OverlappingMarkerSpiderfier.markerStatus.SPIDERFIED:
                        icon = marker.orginalIcon;
                        break;

                      case OverlappingMarkerSpiderfier.markerStatus
                        .UNSPIDERFIABLE:
                        icon = marker.orginalIcon;
                        break;

                      case OverlappingMarkerSpiderfier.markerStatus
                        .UNSPIDERFIED:
                        icon = marker.orginalIcon;
                        break;
                    }
                    marker.setIcon(icon);
                  }
                );

                $.each(marker.listeners, function (index, listener) {
                  if (listener.e === "click") {
                    google.maps.event.removeListener(listener.listener);
                    marker.addListener("spider_click", listener.f);
                  }
                });
                oms.addMarker(marker);
              };

              // Remove if https://github.com/jawj/OverlappingMarkerSpiderfier/issues/103
              // is ever corrected.
              google.maps.event.addListener(map.googleMap, "idle", function () {
                Object.getPrototypeOf(oms).h.call(oms);
              });

              $.each(map.mapMarkers, function (index, marker) {
                geolocationOmsMarkerFunction(marker);
              });

              map.addMarkerAddedCallback(function (marker) {
                geolocationOmsMarkerFunction(marker);
              });
            }
          });

          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {},
  };
})(jQuery, Drupal);
