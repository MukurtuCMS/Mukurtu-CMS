/**
 * @file
 * Javascript for the Leaflet Geolocation map widget.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Leaflet widget.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches Geolocation Maps widget functionality to relevant elements.
   */
  Drupal.behaviors.geolocationLeafletMapsWidget = {
    attach: function (context, drupalSettings) {
      $(once('geolocation-leaflet-maps-widget-processed', '.geolocation-map-widget', context)).each(function (index, item) {
        var widgetId = $(item).attr('id').toString();
        var widget = Drupal.geolocation.widget.getWidgetById(widgetId);
        if (!widget) {
          return;
        }

        widget.map.addCenterUpdatedCallback(function (location, accuracy, identifier) {
          if (typeof identifier === 'undefined') {
            return;
          }

          if (identifier === 'leaflet_control_geocoder') {
            widget.locationAlteredCallback('leaflet-map-feature', location, null);
          }
        });
      });
    },
    detach: function (context, drupalSettings) {}
  };

})(jQuery, Drupal);
