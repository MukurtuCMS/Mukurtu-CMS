/**
 * @file
 * Javascript for leaflet integration.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * GeolocationLeafletMap element.
   *
   * @constructor
   * @augments {GeolocationMapBase}
   * @implements {GeolocationMapInterface}
   * @inheritDoc
   *
   * @prop {Map} leafletMap
   * @prop {L.LayerGroup} markerLayer
   * @prop {TileLayer} tileLayer
   * @prop {Object} settings.leaflet_settings - Leaflet specific settings.
   */
  function GeolocationLeafletMap(mapSettings) {
    var leafletPromise = new Promise(function (resolve, reject) {
      if (typeof L === 'undefined') {
        setTimeout(function () {
          if (typeof L === 'undefined') {
            reject();
          }
          else {
            resolve();
          }
        }, 1000);
      }
      else {
        resolve();
      }
    });

    this.type = 'leaflet';

    Drupal.geolocation.GeolocationMapBase.call(this, mapSettings);

    /**
     *
     * @type {MapOptions}
     */
    var defaultLeafletSettings = {
      zoom: 10
    };

    // Add any missing settings.
    this.settings.leaflet_settings = $.extend(defaultLeafletSettings, this.settings.leaflet_settings);

    // Set the container size.
    this.container.css({
      height: this.settings.leaflet_settings.height,
      width: this.settings.leaflet_settings.width
    });

    var that = this;

    leafletPromise.then(function () {

      var leafletMapSettings = that.settings.leaflet_settings;
      leafletMapSettings.center = [that.lat, that.lng];
      leafletMapSettings.zoomControl = false;
      leafletMapSettings.attributionControl = false;
      leafletMapSettings.crs = L.CRS[that.settings.leaflet_settings.crs];

      /** @type {Map} */
      var leafletMap = L.map(that.container.get(0), leafletMapSettings);

      var markerLayer = L.layerGroup().addTo(leafletMap);

      // Set the tile layer.
      var tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(leafletMap);

      that.leafletMap = leafletMap;
      that.markerLayer = markerLayer;
      that.tileLayer = tileLayer;

      that.addPopulatedCallback(function (map) {
        var singleClick;
        map.leafletMap.on('click', /** @param {LeafletMouseEvent} e */ function (e) {
          singleClick = setTimeout(function () {
            map.clickCallback({lat: e.latlng.lat, lng: e.latlng.lng});
          }, 500);
        });

        map.leafletMap.on('dblclick', /** @param {LeafletMouseEvent} e */ function (e) {
          clearTimeout(singleClick);
          map.doubleClickCallback({lat: e.latlng.lat, lng: e.latlng.lng});
        });

        map.leafletMap.on('contextmenu', /** @param {LeafletMouseEvent} e */ function (e) {
          map.contextClickCallback({lat: e.latlng.lat, lng: e.latlng.lng});
        });

        map.leafletMap.on('moveend', /** @param {LeafletEvent} e */ function (e) {
          map.boundsChangedCallback(map.leafletMap.getBounds());
        });
      });

      that.initializedCallback();
      that.populatedCallback();
    })
    .catch(function (error) {
      console.error(error);
    });
  }
  GeolocationLeafletMap.prototype = Object.create(Drupal.geolocation.GeolocationMapBase.prototype);
  GeolocationLeafletMap.prototype.constructor = GeolocationLeafletMap;
  GeolocationLeafletMap.prototype.getZoom = function () {
    var that = this;
    return new Promise(function (resolve, reject) {
      resolve(that.leafletMap.getZoom());
    });
  };
  GeolocationLeafletMap.prototype.setZoom = function (zoom, defer) {
    if (typeof zoom === 'undefined') {
      zoom = this.settings.leaflet_settings.zoom;
    }
    zoom = parseInt(zoom);
    this.leafletMap.setZoom(zoom);
  };
  GeolocationLeafletMap.prototype.setCenterByCoordinates = function (coordinates, accuracy, identifier) {
    Drupal.geolocation.GeolocationMapBase.prototype.setCenterByCoordinates.call(this, coordinates, accuracy, identifier);

    if (typeof accuracy === 'undefined') {
      this.leafletMap.panTo(coordinates);
      return;
    }

    var circle = this.addAccuracyIndicatorCircle(coordinates, accuracy);

    this.leafletMap.fitBounds(circle.getBounds());

    setInterval(fadeCityCircles, 300);

    function fadeCityCircles() {
      var fillOpacity = circle.options.fillOpacity;
      fillOpacity -= 0.03;

      var opacity = circle.options.opacity;
      opacity -= 0.06;

      if (
          opacity > 0
          && fillOpacity > 0
      ) {
        circle.setStyle({
          fillOpacity: fillOpacity,
          stroke: opacity
        });
      }
      else {
        circle.remove()
      }
    }
  };
  GeolocationLeafletMap.prototype.addAccuracyIndicatorCircle = function (location, accuracy) {
    return L.circle(location, accuracy, {
      interactive: false,
      color: '#4285F4',
      opacity: 0.3,
      fillColor: '#4285F4',
      fillOpacity: 0.15
    }).addTo(this.leafletMap);
  };
  GeolocationLeafletMap.prototype.setMapMarker = function (markerSettings) {
    if (typeof markerSettings.setMarker !== 'undefined') {
      if (markerSettings.setMarker === false) {
        return;
      }
    }

    if (typeof markerSettings.icon === 'string') {
      markerSettings.icon = L.icon({
        iconUrl: markerSettings.icon
      });
    }

    /** @type {Marker} */
    var currentMarker = L.marker([parseFloat(markerSettings.position.lat), parseFloat(markerSettings.position.lng)], markerSettings).addTo(this.markerLayer);

    currentMarker.locationWrapper = markerSettings.locationWrapper;

    if (typeof markerSettings.label === 'string') {
      currentMarker.bindTooltip(markerSettings.label, {
        permanent: true,
        direction: 'top'
      });
    }

    Drupal.geolocation.GeolocationMapBase.prototype.setMapMarker.call(this, currentMarker);

    return currentMarker;
  };
  GeolocationLeafletMap.prototype.removeMapMarker = function (marker) {
    Drupal.geolocation.GeolocationMapBase.prototype.removeMapMarker.call(this, marker);
    this.markerLayer.removeLayer(marker);
  };
  GeolocationLeafletMap.prototype.addShape = function (shapeSettings) {
    if (typeof shapeSettings === 'undefined') {
      return;
    }

    var coordinates = [];

    $.each(shapeSettings.coordinates, function (index, coordinate) {
      coordinates.push([coordinate.lat, coordinate.lng]);
    });

    var shape;

    switch (shapeSettings.shape) {
      case 'line':
        shape = L.polyline(coordinates, {
          color: shapeSettings.strokeColor,
          opacity: shapeSettings.strokeOpacity,
          weight: shapeSettings.strokeWidth
        });
        if (shapeSettings.title) {
          shape.bindTooltip(shapeSettings.title);
        }
        break;

      case 'polygon':
        shape = L.polygon(coordinates, {
          color: shapeSettings.strokeColor,
          opacity: shapeSettings.strokeOpacity,
          weight: shapeSettings.strokeWidth,
          fillColor: shapeSettings.fillColor,
          fillOpacity: shapeSettings.fillOpacity
        });
        if (shapeSettings.title) {
          shape.bindTooltip(shapeSettings.title);
        }
        break;
    }

    shape.addTo(this.leafletMap);

    Drupal.geolocation.GeolocationMapBase.prototype.addShape.call(this, shape);

    return shape;

  };
  GeolocationLeafletMap.prototype.removeShape = function (shape) {
    if (typeof shape === 'undefined') {
      return;
    }
    Drupal.geolocation.GeolocationMapBase.prototype.removeShape.call(this, shape);
    shape.remove();
  };
  GeolocationLeafletMap.prototype.getMarkerBoundaries = function (locations) {

    locations = locations || this.mapMarkers;
    if (locations.length === 0) {
      return;
    }

    var group = new L.featureGroup(locations);

    return group.getBounds();
  };
  GeolocationLeafletMap.prototype.getCenter = function () {
    var center = this.leafletMap.getCenter();
    return {lat: center.lat, lng: center.lng};
  };
  GeolocationLeafletMap.prototype.normalizeBoundaries = function (boundaries) {
    if (boundaries instanceof L.LatLngBounds) {
      return {
        north: boundaries.getNorth(),
        east: boundaries.getEast(),
        south: boundaries.getSouth(),
        west: boundaries.getWest()
      };
    }

    return false;
  };
  GeolocationLeafletMap.prototype.denormalizeBoundaries = function (boundaries) {
    if (typeof boundaries === 'undefined') {
      return false;
    }

    if (boundaries instanceof L.LatLngBounds) {
      return boundaries;
    }

    if (Drupal.geolocation.GeolocationMapBase.prototype.boundariesNormalized.call(this, boundaries)) {
      return L.latLngBounds([
        [boundaries.south, boundaries.west],
        [boundaries.north, boundaries.east]
      ]);
    }
    else {
      boundaries = Drupal.geolocation.GeolocationMapBase.prototype.normalizeBoundaries.call(this, boundaries);
      if (boundaries) {
        return L.latLngBounds([
          [boundaries.south, boundaries.west],
          [boundaries.north, boundaries.east]
        ]);
      }
    }

    return false;
  };
  GeolocationLeafletMap.prototype.fitBoundaries = function (boundaries, identifier) {
    boundaries = this.denormalizeBoundaries(boundaries);
    if (!boundaries) {
      return;
    }

    if (!this.leafletMap.getBounds().equals(boundaries)) {
      this.leafletMap.fitBounds(boundaries);
      Drupal.geolocation.GeolocationMapBase.prototype.fitBoundaries.call(this, boundaries, identifier);
    }
  };
  GeolocationLeafletMap.prototype.addControl = function (element) {
    this.leafletMap.controls = this.leafletMap.controls || [];
    var controlElement = new(L.Control.extend({
      options: {
        position: typeof element.dataset.controlPosition === 'undefined' ? 'topleft' : element.dataset.controlPosition
      },
      onAdd: function (map) {
        element.style.display = 'block';
        L.DomEvent.disableClickPropagation(element);
        return element;
      }
    }));
    controlElement.addTo(this.leafletMap);
    this.leafletMap.controls.push(controlElement);
  };
  GeolocationLeafletMap.prototype.removeControls = function () {
    this.leafletMap.controls = this.leafletMap.controls || [];
    var that = this;
    $.each(this.leafletMap.controls, function (index, control) {
      that.leafletMap.removeControl(control);
    });
  };

  Drupal.geolocation.GeolocationLeafletMap = GeolocationLeafletMap;
  Drupal.geolocation.addMapProvider('leaflet', 'GeolocationLeafletMap');

})(jQuery, Drupal);
