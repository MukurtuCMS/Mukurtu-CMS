/**
 * @file
 * Javascript for the map geocoder widget.
 */

(function (Drupal) {
  'use strict';

  /**
   * GeolocationYandexWidget element.
   *
   * @constructor
   * @augments {GeolocationMapWidgetBase}
   * @implements {GeolocationWidgetInterface}
   * @inheritDoc
   */
  function GeolocationYandexWidget(widgetSettings) {
    Drupal.geolocation.widget.GeolocationMapWidgetBase.call(this, widgetSettings);

    return this;
  }

  GeolocationYandexWidget.prototype = Object.create(Drupal.geolocation.widget.GeolocationMapWidgetBase.prototype);
  GeolocationYandexWidget.prototype.constructor = GeolocationYandexWidget;

  GeolocationYandexWidget.prototype.addMarker = function (location, delta) {
    Drupal.geolocation.widget.GeolocationMapWidgetBase.prototype.addMarker.call(this, location, delta);

    if (typeof delta === 'undefined') {
      delta = this.getNextDelta();
    }

    if (delta === false) {
      return;
    }

    var marker = this.map.setMapMarker({
      position: location
    });
    marker = this.initializeMarker(marker, delta);

    return marker;
  };

  GeolocationYandexWidget.prototype.initializeMarker = function (marker, delta) {
    Drupal.geolocation.widget.GeolocationMapWidgetBase.prototype.initializeMarker.call(this, marker, delta);

    var location = marker.geometry.getCoordinates();
    marker.properties.set('balloonContent', Drupal.t('[@delta] Latitude: @latitude Longitude: @longitude', {
      '@delta': delta,
      '@latitude': location[1],
      '@longitude': location[0]
    }));
    marker.options.draggable = true;
    marker.properties.set('balloonContentHeader', (delta + 1).toString());

    var that = this;
    marker.events.add('dragend', function (e) {
      var location = e.geometry.getCoordinates();
      that.locationAlteredCallback('marker', {
        lat: Number(location[1]),
        lng: Number(location[0])
      }, marker.delta);
    });

    marker.events.add('click', function () {
      that.removeMarker(marker.delta);
      that.locationAlteredCallback('marker', null, marker.delta);
    });

    return marker;
  };

  GeolocationYandexWidget.prototype.updateMarker = function (location, delta) {
    Drupal.geolocation.widget.GeolocationMapWidgetBase.prototype.updateMarker.call(this, location, delta);

    /** @param {Placemark} */
    var marker = this.getMarkerByDelta(delta);
    marker.geometry.setCoordinates([location.lng, location.lat]);

    return marker;
  };

  Drupal.geolocation.widget.GeolocationYandexWidget = GeolocationYandexWidget;

  Drupal.geolocation.widget.addWidgetProvider('yandex', 'GeolocationYandexWidget');

})(Drupal);
