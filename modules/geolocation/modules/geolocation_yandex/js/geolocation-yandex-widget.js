/**
 * @file
 * Javascript for the Yandex Geolocation map widget.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * GoogleMaps widget.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches Geolocation Maps widget functionality to relevant elements.
   */
  Drupal.behaviors.geolocationYandexWidget = {
    attach: function (context, drupalSettings) {
      $(once('geolocation-yandex-widget-processed', '.geolocation-map-widget', context)).each(function (index, item) {
        var widgetId = $(item).attr('id').toString();
        Drupal.geolocation.widget.getWidgetById(widgetId);
      });
    },
    detach: function (context, drupalSettings) {}
  };

})(jQuery, Drupal);
