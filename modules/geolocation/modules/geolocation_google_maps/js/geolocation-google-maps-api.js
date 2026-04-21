/**
 * @file
 * Javascript for the Google Maps API integration.
 */

/**
 * @callback googleLoadedCallback
 */

/**
 * @typedef {Object} Drupal.geolocation.google
 * @property {googleLoadedCallback[]} loadedCallbacks
 */

/**
 * @name GoogleMapSettings
 * @property {String} info_auto_display
 * @property {String} marker_icon_path
 * @property {String} height
 * @property {String} width
 * @property {Number} zoom
 * @property {Number} maxZoom
 * @property {Number} minZoom
 * @property {String} type
 * @property {String} gestureHandling
 * @property {Boolean} panControl
 * @property {Boolean} mapTypeControl
 * @property {Boolean} scaleControl
 * @property {Boolean} streetViewControl
 * @property {Boolean} overviewMapControl
 * @property {Boolean} zoomControl
 * @property {Boolean} rotateControl
 * @property {Boolean} fullscreenControl
 * @property {Object} zoomControlOptions
 * @property {String} mapTypeId
 * @property {String} info_text
 */

(function ($, Drupal) {
  "use strict";

  Drupal.geolocation.google = Drupal.geolocation.google || {};

  /**
   * GeolocationGoogleMap element.
   *
   * @constructor
   * @augments {GeolocationMapBase}
   * @implements {GeolocationMapInterface}
   * @inheritDoc
   *
   * @prop {GoogleMapSettings} settings.google_map_settings - Google Map specific settings.
   * @prop {google.maps.Map} googleMap - Google Map.
   */
  function GeolocationGoogleMap(mapSettings) {
    this.type = "google_maps";

    Drupal.geolocation.GeolocationMapBase.call(this, mapSettings);

    var defaultGoogleSettings = {
      panControl: false,
      scaleControl: false,
      rotateControl: false,
      mapTypeId: "roadmap",
      zoom: 2,
      maxZoom: 20,
      minZoom: 0,
      style: [],
      gestureHandling: "auto",
    };

    // Add any missing settings.
    this.settings.google_map_settings = $.extend(
      defaultGoogleSettings,
      this.settings.google_map_settings
    );

    // Set the container size.
    this.container.css({
      height: this.settings.google_map_settings.height,
      width: this.settings.google_map_settings.width,
    });

    this.addInitializedCallback(function (map) {
      // Get the center point.
      var center = new google.maps.LatLng(map.lat, map.lng);

      /**
       * Create the map object and assign it to the map.
       */
      map.googleMap = new google.maps.Map(map.container[0], {
        zoom: map.settings.google_map_settings.zoom,
        maxZoom: map.settings.google_map_settings.maxZoom,
        minZoom: map.settings.google_map_settings.minZoom,
        center: center,
        mapTypeId: google.maps.MapTypeId[map.settings.google_map_settings.type],
        mapTypeControl: false, // Handled by feature.
        zoomControl: false, // Handled by feature.
        streetViewControl: false, // Handled by feature.
        rotateControl: false, // Handled by feature.
        fullscreenControl: false, // Handled by feature.
        scaleControl: map.settings.google_map_settings.scaleControl,
        panControl: map.settings.google_map_settings.panControl,
        gestureHandling: map.settings.google_map_settings.gestureHandling,
        styles:
          typeof map.settings.google_map_settings.style !== "undefined"
            ? map.settings.google_map_settings.style
            : null,
      });

      var singleClick;
      var timer;
      google.maps.event.addListener(map.googleMap, "click", function (e) {
        // Create 500ms timeout to wait for double click.
        singleClick = setTimeout(function () {
          map.clickCallback({ lat: e.latLng.lat(), lng: e.latLng.lng() });
        }, 500);
        timer = Date.now();
      });

      google.maps.event.addListener(map.googleMap, "dblclick", function (e) {
        clearTimeout(singleClick);
        map.doubleClickCallback({ lat: e.latLng.lat(), lng: e.latLng.lng() });
      });

      google.maps.event.addListener(map.googleMap, "rightclick", function (e) {
        map.contextClickCallback({ lat: e.latLng.lat(), lng: e.latLng.lng() });
      });

      google.maps.event.addListenerOnce(
        map.googleMap,
        "tilesloaded",
        function () {
          map.populatedCallback();
        }
      );

      google.maps.event.addListener(
        map.googleMap,
        "bounds_changed",
        function () {
          map.boundsChangedCallback(map.googleMap.getBounds());
        }
      );
    });

    if (this.initialized) {
      this.initializedCallback();
    } else {
      var that = this;
      Drupal.geolocation.google.addLoadedCallback(function () {
        that.initializedCallback();
      });

      // Load Google Maps API and execute all callbacks.
      Drupal.geolocation.google.load();
    }
  }
  GeolocationGoogleMap.prototype = Object.create(
    Drupal.geolocation.GeolocationMapBase.prototype
  );
  GeolocationGoogleMap.prototype.constructor = GeolocationGoogleMap;
  GeolocationGoogleMap.prototype.setMapMarker = function (markerSettings) {
    if (typeof markerSettings.setMarker !== "undefined") {
      if (markerSettings.setMarker === false) {
        return;
      }
    }

    markerSettings.position = new google.maps.LatLng(
      Number(markerSettings.position.lat),
      Number(markerSettings.position.lng)
    );
    markerSettings.map = this.googleMap;

    if (
      typeof this.settings.google_map_settings.marker_icon_path === "string"
    ) {
      if (
        this.settings.google_map_settings.marker_icon_path &&
        typeof markerSettings.icon === "undefined"
      ) {
        markerSettings.icon =
          this.settings.google_map_settings.marker_icon_path;
      }
    }

    /** @type {google.maps.Marker} */
    var currentMarker = new google.maps.Marker(markerSettings);

    Drupal.geolocation.GeolocationMapBase.prototype.setMapMarker.call(
      this,
      currentMarker
    );

    return currentMarker;
  };
  GeolocationGoogleMap.prototype.removeMapMarker = function (marker) {
    if (typeof marker === "undefined") {
      return;
    }
    Drupal.geolocation.GeolocationMapBase.prototype.removeMapMarker.call(
      this,
      marker
    );
    marker.setMap(null);
  };
  GeolocationGoogleMap.prototype.addShape = function (shapeSettings) {
    if (typeof shapeSettings === "undefined") {
      return;
    }

    var shape;

    switch (shapeSettings.shape) {
      case "line":
        shape = new google.maps.Polyline({
          path: shapeSettings.coordinates,
          strokeColor: shapeSettings.strokeColor,
          strokeOpacity: shapeSettings.strokeOpacity,
          strokeWeight: shapeSettings.strokeWidth,
        });
        break;

      case "polygon":
        shape = new google.maps.Polygon({
          paths: shapeSettings.coordinates,
          strokeColor: shapeSettings.strokeColor,
          strokeOpacity: shapeSettings.strokeOpacity,
          strokeWeight: shapeSettings.strokeWidth,
          fillColor: shapeSettings.fillColor,
          fillOpacity: shapeSettings.fillOpacity,
        });
        break;
    }

    if (
      typeof shapeSettings.title !== "undefined" &&
      shapeSettings.title.length
    ) {
      var infoWindow = new google.maps.InfoWindow();
      var that = this;
      google.maps.event.addListener(shape, "mouseover", function (e) {
        infoWindow.setPosition(e.latLng);
        infoWindow.setContent(shapeSettings.title);
        infoWindow.open(that.googleMap);
      });
      google.maps.event.addListener(shape, "mouseout", function () {
        infoWindow.close();
      });
    }

    shape.setMap(this.googleMap);
    Drupal.geolocation.GeolocationMapBase.prototype.addShape.call(this, shape);

    return shape;
  };
  GeolocationGoogleMap.prototype.removeShape = function (shape) {
    if (typeof shape === "undefined") {
      return;
    }
    Drupal.geolocation.GeolocationMapBase.prototype.removeShape.call(
      this,
      shape
    );
    shape.setMap(null);
  };
  GeolocationGoogleMap.prototype.getMarkerBoundaries = function (locations) {
    locations = locations || this.mapMarkers;
    if (locations.length === 0) {
      return false;
    }

    // A Google Maps API tool to re-center the map on its content.
    var bounds = new google.maps.LatLngBounds();

    $.each(
      locations,

      /**
       * @param {integer} index - Current index.
       * @param {google.maps.Marker} item - Current marker.
       */
      function (index, item) {
        bounds.extend(item.getPosition());
      }
    );
    return bounds;
  };
  GeolocationGoogleMap.prototype.fitBoundaries = function (
    boundaries,
    identifier
  ) {
    boundaries = this.denormalizeBoundaries(boundaries);
    if (!boundaries) {
      return;
    }

    var currentBounds = this.googleMap.getBounds();
    if (!currentBounds || !currentBounds.equals(boundaries)) {
      this.googleMap.fitBounds(boundaries);
      Drupal.geolocation.GeolocationMapBase.prototype.fitBoundaries.call(
        this,
        boundaries,
        identifier
      );
    }
  };
  GeolocationGoogleMap.prototype.getZoom = function () {
    var that = this;
    return new Promise(function (resolve, reject) {
      google.maps.event.addListenerOnce(that.googleMap, "idle", function () {
        resolve(that.googleMap.getZoom());
      });
    });
  };
  GeolocationGoogleMap.prototype.setZoom = function (zoom, defer) {
    if (typeof zoom === "undefined") {
      zoom = this.settings.google_map_settings.zoom;
    }

    zoom = parseInt(zoom);

    this.googleMap.setZoom(zoom);
    var that = this;
    if (defer) {
      google.maps.event.addListenerOnce(this.googleMap, "idle", function () {
        that.googleMap.setZoom(zoom);
      });
    }
  };
  GeolocationGoogleMap.prototype.getCenter = function () {
    var center = this.googleMap.getCenter();
    return { lat: center.lat(), lng: center.lng() };
  };
  GeolocationGoogleMap.prototype.setCenterByCoordinates = function (
    coordinates,
    accuracy,
    identifier
  ) {
    Drupal.geolocation.GeolocationMapBase.prototype.setCenterByCoordinates.call(
      this,
      coordinates,
      accuracy,
      identifier
    );

    if (typeof accuracy === "undefined") {
      this.googleMap.setCenter(coordinates);
      return;
    }

    var circle = this.addAccuracyIndicatorCircle(coordinates, accuracy);

    // Set the zoom level to the accuracy circle's size.
    this.googleMap.fitBounds(circle.getBounds());

    // Fade circle away.
    setInterval(fadeCityCircles, 200);

    function fadeCityCircles() {
      var fillOpacity = circle.get("fillOpacity");
      fillOpacity -= 0.01;

      var strokeOpacity = circle.get("strokeOpacity");
      strokeOpacity -= 0.02;

      if (strokeOpacity > 0 && fillOpacity > 0) {
        circle.setOptions({
          fillOpacity: fillOpacity,
          strokeOpacity: strokeOpacity,
        });
      } else {
        circle.setMap(null);
      }
    }
  };
  GeolocationGoogleMap.prototype.normalizeBoundaries = function (boundaries) {
    if (boundaries instanceof google.maps.LatLngBounds) {
      var northEast = boundaries.getNorthEast();
      var southWest = boundaries.getSouthWest();

      return {
        north: northEast.lat(),
        east: northEast.lng(),
        south: southWest.lat(),
        west: southWest.lng(),
      };
    }

    return false;
  };
  GeolocationGoogleMap.prototype.denormalizeBoundaries = function (boundaries) {
    if (typeof boundaries === "undefined") {
      return false;
    }

    if (boundaries instanceof google.maps.LatLngBounds) {
      return boundaries;
    }

    if (
      Drupal.geolocation.GeolocationMapBase.prototype.boundariesNormalized.call(
        this,
        boundaries
      )
    ) {
      return new google.maps.LatLngBounds(
        { lat: boundaries.south, lng: boundaries.west },
        { lat: boundaries.north, lng: boundaries.east }
      );
    } else {
      boundaries =
        Drupal.geolocation.GeolocationMapBase.prototype.normalizeBoundaries.call(
          this,
          boundaries
        );
      if (boundaries) {
        return new google.maps.LatLngBounds(
          { lat: boundaries.south, lng: boundaries.west },
          { lat: boundaries.north, lng: boundaries.east }
        );
      }
    }

    return false;
  };
  GeolocationGoogleMap.prototype.addControl = function (element) {
    element = $(element);

    var position = google.maps.ControlPosition.TOP_LEFT;

    if (typeof element.data("googleMapControlPosition") !== "undefined") {
      var customPosition = element.data("googleMapControlPosition");
      if (typeof google.maps.ControlPosition[customPosition] !== "undefined") {
        position = google.maps.ControlPosition[customPosition];
      }
    }

    var controlAlreadyAdded = false;
    var controlIndex = 0;
    this.googleMap.controls[position].forEach(function (controlElement, index) {
      var control = $(controlElement);
      if (
        element[0].getAttribute("class") === control[0].getAttribute("class")
      ) {
        controlAlreadyAdded = true;
        controlIndex = index;
      }
    });

    if (!controlAlreadyAdded) {
      element.show();
      this.googleMap.controls[position].push(element[0]);
      return element;
    } else {
      element.remove();

      return this.googleMap.controls[position].getAt(controlIndex);
    }
  };
  GeolocationGoogleMap.prototype.removeControls = function () {
    $.each(this.googleMap.controls, function (index, item) {
      if (typeof item === "undefined") {
        return;
      }

      if (typeof item.clear === "function") {
        item.clear();
      }
    });
  };

  Drupal.geolocation.GeolocationGoogleMap = GeolocationGoogleMap;
  Drupal.geolocation.addMapProvider("google_maps", "GeolocationGoogleMap");

  /**
   * Draw a circle representing the accuracy radius of HTML5 geolocation.
   *
   * @param {GeolocationCoordinates|google.maps.LatLng} location - Location to center on.
   * @param {int} accuracy - Accuracy in m.
   *
   * @return {google.maps.Circle} - Indicator circle.
   */
  GeolocationGoogleMap.prototype.addAccuracyIndicatorCircle = function (
    location,
    accuracy
  ) {
    return new google.maps.Circle({
      center: location,
      radius: accuracy,
      map: this.googleMap,
      fillColor: "#4285F4",
      fillOpacity: 0.15,
      strokeColor: "#4285F4",
      strokeOpacity: 0.3,
      strokeWeight: 1,
      clickable: false,
    });
  };

  /**
   * @inheritDoc
   */
  Drupal.geolocation.google.addLoadedCallback = function (callback) {
    Drupal.geolocation.google.loadedCallbacks =
      Drupal.geolocation.google.loadedCallbacks || [];
    Drupal.geolocation.google.loadedCallbacks.push(callback);
  };

  /**
   * Provides the callback that is called when maps loads.
   */
  Drupal.geolocation.google.load = function () {
    // Check for Google Maps.
    if (typeof google === "undefined" || typeof google.maps === "undefined") {
      return;
    }

    $.each(
      Drupal.geolocation.google.loadedCallbacks,
      function (index, callback) {
        callback();
      }
    );
    Drupal.geolocation.google.loadedCallbacks = [];
  };
})(jQuery, Drupal);
