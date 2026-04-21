/**
 * @file
 * Javascript for HERE Maps integration.
 */

(function ($, Drupal) {
  'use strict';

  /* global H */

  /**
   * @typedef {Object} HereMapSettings
   * @property hereMapsAppId
   * @property hereMapsAppCode
   */

  /**
   * GeolocationHereMap element.
   *
   * @constructor
   * @augments {GeolocationMapBase}
   * @implements {GeolocationMapInterface}
   * @inheritDoc
   *
   * @prop {Object} settings.here_settings - HERE Maps specific settings.
   * @prop {H.Map} hereMap
   */
  function GeolocationHereMap(mapSettings) {
    if (typeof H === 'undefined') {
      console.error('HERE Maps library not loaded. Bailing out.'); // eslint-disable-line no-console.
      return;
    }

    this.type = 'here';

    Drupal.geolocation.GeolocationMapBase.call(this, mapSettings);

    var defaultHereSettings = {
      zoom: 10
    };

    // Add any missing settings.
    this.settings.here_settings = $.extend(defaultHereSettings, this.settings.here_settings);

    // Set the container size.
    this.container.css({
      height: this.settings.here_settings.height,
      width: this.settings.here_settings.width
    });

    // Initialize the platform object:
    var platform = new H.service.Platform({
      app_id: drupalSettings.geolocation.hereMapsAppId,
      app_code: drupalSettings.geolocation.hereMapsAppCode,
      useHTTPS: true
    });

    var pixelRatio = window.devicePixelRatio || 1;
    var defaultLayers = platform.createDefaultLayers({
      tileSize: pixelRatio === 1 ? 256 : 512,
      ppi: pixelRatio === 1 ? undefined : 320
    });

    // Instantiate (and display) a map object:
    this.hereMap = new H.Map(
      this.container.get(0),
      defaultLayers.normal.map,
      {
        zoom: this.settings.here_settings.zoom,
        center: { lng: this.lng, lat: this.lat },
        pixelRatio: pixelRatio
      }
    );

    new H.mapevents.Behavior(new H.mapevents.MapEvents(this.hereMap));

    H.ui.UI.createDefault(this.hereMap, defaultLayers);

    this.addPopulatedCallback(function (map) {
      map.hereMap.addEventListener('tap', function (e) {
        var coord = map.hereMap.screenToGeo(e.currentPointer.viewportX, e.currentPointer.viewportY);
        map.clickCallback({lat: coord.lat, lng: coord.lng});
      });

      map.hereMap.addEventListener('contextmenu', function (e) {
        var coord = map.hereMap.screenToGeo(e.viewportX, e.viewportY);
        map.contextClickCallback({lat: coord.lat, lng: coord.lng});
      });
    });

    this.initializedCallback();
    this.populatedCallback();
  }
  GeolocationHereMap.prototype = Object.create(Drupal.geolocation.GeolocationMapBase.prototype);
  GeolocationHereMap.prototype.constructor = GeolocationHereMap;
  GeolocationHereMap.prototype.getZoom = function () {
    var that = this;
    return new Promise(function (resolve, reject) {
      resolve(that.hereMap.getZoom());
    });
  };
  GeolocationHereMap.prototype.setZoom = function (zoom, defer) {
    if (typeof zoom === 'undefined') {
      zoom = this.settings.here_settings.zoom;
    }
    zoom = parseInt(zoom);
    this.hereMap.setZoom(zoom);
  };
  GeolocationHereMap.prototype.setCenterByCoordinates = function (coordinates, accuracy, identifier) {
    Drupal.geolocation.GeolocationMapBase.prototype.setCenterByCoordinates.call(this, coordinates, accuracy, identifier);
    this.hereMap.setCenter(coordinates);
  };
  GeolocationHereMap.prototype.setMapMarker = function (markerSettings) {
    var hereMarkerSettings = {
      title: markerSettings.title
    };

    if (typeof markerSettings.icon === 'string') {
      hereMarkerSettings.icon = new H.map.Icon(markerSettings.icon);
    }

    var currentMarker = new H.map.Marker({ lat: parseFloat(markerSettings.position.lat), lng: parseFloat(markerSettings.position.lng) }, hereMarkerSettings);

    this.hereMap.addObject(currentMarker);

    currentMarker.locationWrapper = markerSettings.locationWrapper;

    Drupal.geolocation.GeolocationMapBase.prototype.setMapMarker.call(this, currentMarker);

    return currentMarker;
  };
  GeolocationHereMap.prototype.removeMapMarker = function (marker) {
    Drupal.geolocation.GeolocationMapBase.prototype.removeMapMarker.call(this, marker);
    this.hereMap.removeObject(marker);
  };
  GeolocationHereMap.prototype.addShape = function (shapeSettings) {
    if (typeof shapeSettings === 'undefined') {
      return;
    }

    var shape;

    var lineString = new H.geo.LineString();
    $.each(shapeSettings.coordinates, function (index, item) {
      lineString.pushPoint(item);
    });

    switch (shapeSettings.shape) {
      case 'line':
        shape = new H.map.Polyline(lineString, {
          style: {
            strokeColor: 'rgba(' + parseInt(shapeSettings.strokeColor.substring(1,3), 16) + ', ' + parseInt(shapeSettings.strokeColor.substring(3,5), 16) + ', ' + parseInt(shapeSettings.strokeColor.substring(5,7), 16) + ', ' + shapeSettings.strokeOpacity + ')',
            lineWidth: shapeSettings.strokeWidth
          }
        });
        break;

      case 'polygon':
        shape = new H.map.Polygon(lineString, {
          style: {
            strokeColor: 'rgba(' + parseInt(shapeSettings.strokeColor.substring(1,3), 16) + ', ' + parseInt(shapeSettings.strokeColor.substring(3,5), 16) + ', ' + parseInt(shapeSettings.strokeColor.substring(5,7), 16) + ', ' + shapeSettings.strokeOpacity + ')',
            lineWidth: shapeSettings.strokeWidth,
            fillColor: 'rgba(' + parseInt(shapeSettings.fillColor.substring(1,3), 16) + ', ' + parseInt(shapeSettings.fillColor.substring(3,5), 16) + ', ' + parseInt(shapeSettings.fillColor.substring(5,7), 16) + ', ' + shapeSettings.fillOpacity + ')'
          }
        });
        break;
    }

    this.hereMap.addObject(shape);
    Drupal.geolocation.GeolocationMapBase.prototype.addShape.call(this, shape);

    return shape;

  };
  GeolocationHereMap.prototype.removeShape = function (shape) {
    if (typeof shape === 'undefined') {
      return;
    }
    Drupal.geolocation.GeolocationMapBase.prototype.removeShape.call(this, shape);
    this.hereMap.removeObject(shape);
  };
  GeolocationHereMap.prototype.fitBoundaries = function (boundaries, identifier) {
    boundaries = this.denormalizeBoundaries(boundaries);
    if (!boundaries) {
      return;
    }

    if (!this.hereMap.getViewBounds().equals(boundaries)) {
      this.hereMap.setViewBounds(boundaries);
      Drupal.geolocation.GeolocationMapBase.prototype.fitBoundaries.call(this, boundaries, identifier);
    }
  };
  GeolocationHereMap.prototype.getMarkerBoundaries = function (locations) {

    locations = locations || this.mapMarkers;
    if (locations.length === 0) {
      return;
    }

    var points = new H.geo.MultiPoint([]);
    $.each(
      locations,
        /**
         *
         * @param index
         * @param {H.map.Marker} marker
         */
      function (index, marker) {
        points.push(marker.getPosition());
      }
    );
    return points.getBounds();
  };
  GeolocationHereMap.prototype.getCenter = function () {
    var center = this.hereMap.getCenter();
    return {lat: center.lat, lng: center.lng};
  };
  GeolocationHereMap.prototype.normalizeBoundaries = function (boundaries) {
    if (boundaries instanceof H.geo.Rect) {
      return {
        north: boundaries.getTop(),
        east: boundaries.getLeft(),
        south: boundaries.getBottom(),
        west: boundaries.getRight()
      };
    }

    return false;
  };
  GeolocationHereMap.prototype.denormalizeBoundaries = function (boundaries) {
    if (typeof boundaries === 'undefined') {
      return false;
    }

    if (boundaries instanceof H.geo.Rect) {
      return boundaries;
    }

    if (Drupal.geolocation.GeolocationMapBase.prototype.boundariesNormalized.call(this, boundaries)) {
      return new H.geo.Rect(boundaries.north, boundaries.west, boundaries.south, boundaries.east);
    }
    else {
      boundaries = Drupal.geolocation.GeolocationMapBase.prototype.normalizeBoundaries.call(this, boundaries);
      if (boundaries) {
        return new H.geo.Rect(boundaries.north, boundaries.west, boundaries.south, boundaries.east);
      }
    }

    return false;
  };

  Drupal.geolocation.GeolocationHereMap = GeolocationHereMap;
  Drupal.geolocation.addMapProvider('here', 'GeolocationHereMap');

})(jQuery, Drupal);
