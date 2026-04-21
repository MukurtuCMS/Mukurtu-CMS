/**
 * @file
 * Javascript for Baidu Maps integration.
 */

(function ($, Drupal) {
  'use strict';

  /* global H */

  /**
   * @typedef {Object} BaiduMapSettings
   * @property baiduMapsAppId
   * @property baiduMapsAppCode
   */

  /**
   * GeolocationBaiduMap element.
   *
   * @constructor
   * @augments {GeolocationMapBase}
   * @implements {GeolocationMapInterface}
   * @inheritDoc
   *
   * @prop {Object} settings.baidu_settings - Baidu Maps specific settings.
   * @prop {BMap.Map} baiduMap
   */
  function GeolocationBaiduMap(mapSettings) {
    if (typeof BMap === 'undefined') {
      console.error('Baidu Maps library not loaded. Bailing out.'); // eslint-disable-line no-console.
      return;
    }

    this.type = 'baidu';

    Drupal.geolocation.GeolocationMapBase.call(this, mapSettings);

    var defaultBaiduSettings = {
      zoom: 10
    };

    // Add any missing settings.
    this.settings.baidu_settings = $.extend(defaultBaiduSettings, this.settings.baidu_settings);

    // Set the container size.
    this.container.css({
      height: this.settings.baidu_settings.height,
      width: this.settings.baidu_settings.width
    });

    var that = this;

    // Instantiate (and display) a map object:
    this.baiduMap = new BMap.Map(this.container.get(0));
    this.baiduMap.centerAndZoom(new BMap.Point(0, 0), 10);

    // TODO: Centrering & Zooming.
    this.baiduMap.enableScrollWheelZoom();

    this.baiduMap.addEventListener('click', function (e) {
      that.clickCallback({lat: e.point.lat, lng: e.point.lng});
    });

    this.baiduMap.addEventListener('moveend', function () {
      that.boundsChangedCallback(that.baiduMap.getBounds());
    });

    this.initializedCallback();
    this.populatedCallback();
  }
  GeolocationBaiduMap.prototype = Object.create(Drupal.geolocation.GeolocationMapBase.prototype);
  GeolocationBaiduMap.prototype.constructor = GeolocationBaiduMap;
  GeolocationBaiduMap.prototype.getZoom = function () {
    var that = this;
    return new Promise(function (resolve, reject) {
      resolve(that.baiduMap.getZoom());
    });
  };
  GeolocationBaiduMap.prototype.setZoom = function (zoom, defer) {
    if (typeof zoom === 'undefined') {
      zoom = this.settings.baidu_settings.zoom;
    }
    zoom = parseInt(zoom);
    this.baiduMap.setZoom(zoom);
  };
  GeolocationBaiduMap.prototype.setCenterByCoordinates = function (coordinates, accuracy, identifier) {
    Drupal.geolocation.GeolocationMapBase.prototype.setCenterByCoordinates.call(this, coordinates, accuracy, identifier);
    this.baiduMap.setCenter(new BMap.Point(coordinates.lng, coordinates.lat));
  };
  GeolocationBaiduMap.prototype.setMapMarker = function (markerSettings) {
    var baiduMarkerSettings = {
      title: markerSettings.title
    };

    if (typeof markerSettings.icon === 'string') {
      baiduMarkerSettings.icon = new BMap.Icon(markerSettings.icon, new BMap.Size(300,157));
    }
    var currentMarker = new BMap.Marker(new BMap.Point(parseFloat(markerSettings.position.lng), parseFloat(markerSettings.position.lat)), baiduMarkerSettings);
    this.baiduMap.addOverlay(currentMarker);

    currentMarker.locationWrapper = markerSettings.locationWrapper;

    Drupal.geolocation.GeolocationMapBase.prototype.setMapMarker.call(this, currentMarker);
    return currentMarker;
  };
  GeolocationBaiduMap.prototype.removeMapMarker = function (marker) {
    Drupal.geolocation.GeolocationMapBase.prototype.removeMapMarker.call(this, marker);
    this.baiduMap.removeOverlay(marker);
  };
  GeolocationBaiduMap.prototype.addShape = function (shapeSettings) {
    if (typeof shapeSettings === 'undefined') {
      return;
    }

    var shape;

    switch (shapeSettings.shape) {
      case 'line':
        shape = new BMap.Polyline(shapeSettings.coordinates, {
          strokeColor: shapeSettings.strokeColor,
          strokeOpacity: shapeSettings.strokeOpacity,
          strokeWeight: shapeSettings.strokeWidth
        });
        break;

      case 'polygon':
        shape = new BMap.Polygon(shapeSettings.coordinates, {
          strokeOpacity: shapeSettings.strokeOpacity,
          strokeWeight: shapeSettings.strokeWidth,
          fillColor: shapeSettings.fillColor,
          fillOpacity: shapeSettings.fillOpacity
        });
        break;
    }

    this.baiduMap.addOverlay(shape);
    Drupal.geolocation.GeolocationMapBase.prototype.addShape.call(this, shape);

    return shape;

  };
  GeolocationBaiduMap.prototype.removeShape = function (shape) {
    if (typeof shape === 'undefined') {
      return;
    }
    Drupal.geolocation.GeolocationMapBase.prototype.removeShape.call(this, shape);
    this.baiduMap.removeOverlay(shape);
  };
  GeolocationBaiduMap.prototype.fitBoundaries = function (boundaries, identifier) {
    boundaries = this.denormalizeBoundaries(boundaries);
    if (!boundaries) {
      return;
    }

    if (!this.baiduMap.getBounds().equals(boundaries)) {
      this.baiduMap.setViewport([boundaries.getNorthEast(), boundaries.getSouthWest()]);
      Drupal.geolocation.GeolocationMapBase.prototype.fitBoundaries.call(this, boundaries, identifier);
    }
  };
  GeolocationBaiduMap.prototype.getMarkerBoundaries = function (locations) {

    locations = locations || this.mapMarkers;
    if (locations.length === 0) {
      return;
    }

    var points = [];
    $.each(
      locations,
        /**
         *
         * @param index
         * @param {BMap.Marker} marker
         */
      function (index, marker) {
        points.push(marker.getPosition());
      }
    );

    if (points.length === 0) {
      return;
    }

    var bounds = new BMap.Bounds(points[0], points[0]);

    $.each(
      points,
      function (index, point) {
        bounds.extend(point);
      }
    );

    return bounds;
  };
  GeolocationBaiduMap.prototype.getCenter = function () {
    var center = this.baiduMap.getCenter();
    return {lat: center.lat, lng: center.lng};
  };
  GeolocationBaiduMap.prototype.normalizeBoundaries = function (boundaries) {
    if (boundaries instanceof BMap.Bounds) {
      return {
        north: boundaries.getNorthEast().lat,
        east: boundaries.getNorthEast().lng,
        south: boundaries.getSouthWest().lat,
        west: boundaries.getSouthWest().lng
      };
    }

    return false;
  };
  GeolocationBaiduMap.prototype.denormalizeBoundaries = function (boundaries) {
    if (typeof boundaries === 'undefined') {
      return false;
    }

    if (boundaries instanceof BMap.Bounds) {
      return boundaries;
    }

    if (Drupal.geolocation.GeolocationMapBase.prototype.boundariesNormalized.call(this, boundaries)) {
      return new BMap.Bounds(boundaries.north, boundaries.west, boundaries.south, boundaries.east);
    }
    else {
      boundaries = Drupal.geolocation.GeolocationMapBase.prototype.normalizeBoundaries.call(this, boundaries);
      if (boundaries) {
        return new BMap.Bounds(new BMap.Point(boundaries.north, boundaries.west), new BMap.Point(boundaries.south, boundaries.east));
      }
    }

    return false;
  };

  Drupal.geolocation.GeolocationBaiduMap = GeolocationBaiduMap;
  Drupal.geolocation.addMapProvider('baidu', 'GeolocationBaiduMap');

})(jQuery, Drupal);
