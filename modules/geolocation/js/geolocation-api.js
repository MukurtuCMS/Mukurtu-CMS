/**
 * @file
 * Javascript for the geolocation module.
 */

/**
 * @typedef {Object} GeolocationSettings
 *
 * @property {GeolocationMapSettings[]} maps
 * @property {Object} mapCenter
 */

/**
 * @type {GeolocationSettings} drupalSettings.geolocation
 */

/**
 * @typedef {Object} GeolocationMapSettings
 *
 * @property {String} [type] Map type
 * @property {String} id
 * @property {Object} settings
 * @property {Number} lat
 * @property {Number} lng
 * @property {Object[]} map_center
 * @property {jQuery} wrapper
 * @property {GeolocationMapMarker[]} mapMarkers
 * @property {GeolocationShape[]} mapShapes
 */

/**
 * Callback when map is clicked.
 *
 * @callback GeolocationMapClickCallback
 *
 * @param {GeolocationCoordinates} location - Click location.
 */

/**
 * Callback when a marker is added or removed.
 *
 * @callback GeolocationMarkerCallback
 *
 * @param {GeolocationMapMarker} marker - Map marker.
 */

/**
 * Callback when map is right-clicked.
 *
 * @callback GeolocationMapContextClickCallback
 *
 * @param {GeolocationCoordinates} location - Click location.
 */

/**
 * Callback when map provider becomes available.
 *
 * @callback GeolocationMapInitializedCallback
 *
 * @param {GeolocationMapInterface} map - Geolocation map.
 */

/**
 * Callback when map bounds changed.
 *
 * @callback GeolocationBoundsChangedCallback
 *
 * @param object bounds - New bounds.
 */

/**
 * Callback when map fully loaded.
 *
 * @callback GeolocationMapPopulatedCallback
 *
 * @param {GeolocationMapInterface} map - Geolocation map.
 */

/**
 * Callback when and only when map is updated.
 *
 * @callback GeolocationMapUpdatedCallback
 *
 * @param {GeolocationMapInterface, GeolocationMapSettings} map - Geolocation map.
 */

/**
 * @typedef {Object} GeolocationCoordinates
 *
 * @property {Number} lat
 * @property {Number} lng
 */

/**
 * @typedef {Object} GeolocationCenterOption
 *
 * @property {Object} map_center_id
 * @property {Object} option_id
 * @property {Object} settings
 */

/**
 * @typedef {Object} GeolocationMapMarker
 *
 * @property {GeolocationCoordinates} position
 * @property {string} title
 * @property {boolean} [setMarker]
 * @property {string} [icon]
 * @property {string} [label]
 * @property {jQuery} locationWrapper
 */

/**
 * @typedef {Object} GeolocationShape
 *
 * @property {GeolocationCoordinates[]} coordinates
 * @property {jQuery} shapeWrapper
 * @property {string} shape
 * @property {string} [title]
 * @property {string} [strokeColor]
 * @property {int} [strokeWidth]
 * @property {number} [strokeOpacity]
 * @property {string} [fillColor]
 * @property {number} [fillOpacity]
 */

/**
 * Interface for classes that represent a color.
 *
 * @interface GeolocationMapInterface
 *
 * @property {Boolean} initialized - True when map provider available and initializedCallbacks executed.
 * @property {Boolean} loaded - True when map fully loaded and all loadCallbacks executed.
 * @property {String} id
 * @property {GeolocationMapSettings} settings
 * @property {Number} lat
 * @property {Number} lng
 * @property {Object[]} mapCenter
 * @property {jQuery} wrapper
 * @property {jQuery} container
 * @property {Object[]} mapMarkers
 *
 * @property {function({jQuery}):{jQuery}} addControl - Add control to map, identified by classes.
 * @property {function()} removeControls - Remove controls from map.
 *
 * @property {function()} populatedCallback - Executes {GeolocationMapPopulatedCallback[]} for this map.
 * @property {function({GeolocationMapPopulatedCallback})} addPopulatedCallback - Adds a callback that will be called when map is fully loaded.
 * @property {function()} initializedCallback - Executes {GeolocationMapInitializedCallbacks[]} for this map.
 * @property {function({GeolocationMapInitializedCallback})} addInitializedCallback - Adds a callback that will be called when map provider becomes available.
 * @property {function({GeolocationMapSettings})} updatedCallback - Executes {GeolocationMapUpdatedCallbacks[]} for this map.
 * @property {function({GeolocationMapUpdatedCallbacks})} addUpdatedCallback - Adds a callback that will be called when and only when an already existing map is updated.
 *
 * @property {function({GeolocationMapMarker}):{GeolocationMapMarker}} setMapMarker - Set marker on map.
 * @property {function({GeolocationMapMarker})} removeMapMarker - Remove single marker.
 * @property {function()} removeMapMarkers - Remove all markers from map.
 *
 * @property {function({GeolocationShape})} addShape - Add shape to map.
 * @property {function({GeolocationShape})} removeShape - Remove shape from map.
 * @property {function()} removeShapes - Remove all shapes from map.
 *
 * @property {function():{Promise}} getZoom - Get zoom.
 * @property {function({string}?, {Boolean}?)} setZoom - Set zoom.
 * @property {function():{GeolocationCoordinates}} getCenter - Get map center coordinates.
 * @property {function({string})} setCenter - Center map by plugin.
 * @property {function({GeolocationCoordinates}, {Number}?, {string}?)} setCenterByCoordinates - Center map on coordinates.
 * @property {function({GeolocationMapMarker}[]?, {String}?)} fitMapToMarkers - Fit map to markers.
 * @property {function({GeolocationMapMarker}[]?):{Object}} getMarkerBoundaries - Get marker boundaries.
 * @property {function({Object}, {String}?)} fitBoundaries - Fit map to bounds.
 *
 * @property {function({Event})} clickCallback - Executes {GeolocationMapClickCallbacks} for this map.
 * @property {function({GeolocationMapClickCallback})} addClickCallback - Adds a callback that will be called when map is clicked.
 *
 * @property {function({Event})} doubleClickCallback - Executes {GeolocationMapClickCallbacks} for this map.
 * @property {function({GeolocationMapClickCallback})} addDoubleClickCallback - Adds a callback that will be called on double click.
 *
 * @property {function({Event})} contextClickCallback - Executes {GeolocationMapContextClickCallbacks} for this map.
 * @property {function({GeolocationMapContextClickCallback})} addContextClickCallback - Adds a callback that will be called when map is clicked.
 *
 * @property {function({GeolocationMapMarker})} markerAddedCallback - Executes {GeolocationMarkerCallback} for this map.
 * @property {function({GeolocationMarkerCallback})} addMarkerAddedCallback - Adds a callback that will be called on marker(s) being added.
 *
 * @property {function({GeolocationMapMarker})} markerRemoveCallback - Executes {GeolocationMarkerCallback} for this map.
 * @property {function({GeolocationMarkerCallback})} addMarkerRemoveCallback - Adds a callback that will be called before marker is removed.
 *
 * @property {function()} boundsChangedCallback - Executes {GeolocationBoundsChangedCallback} for this map.
 * @property {function({GeolocationBoundsChangedCallback})} addBoundsChangedCallback - Adds a callback that will be called when map bounds changed.
 */

/**
 * Geolocation map API.
 *
 * @implements {GeolocationMapInterface}
 */
(
  function ($, Drupal) {
    "use strict";

    /**
     * @namespace
     * @prop {Object} Drupal.geolocation
     */
    Drupal.geolocation = Drupal.geolocation || {};

    /**
     * @type {GeolocationMapInterface[]}
     * @prop {GeolocationMapSettings} settings The map settings.
     */
    Drupal.geolocation.maps = Drupal.geolocation.maps || [];

    Drupal.geolocation.mapCenter = Drupal.geolocation.mapCenter || {};

    /**
     * Geolocation map.
     *
     * @constructor
     * @abstract
     * @implements {GeolocationMapInterface}
     *
     * @param {GeolocationMapSettings} mapSettings Setting to create map.
     */
    function GeolocationMapBase(mapSettings) {
      this.settings = mapSettings.settings || {};
      this.wrapper = mapSettings.wrapper;
      this.container = mapSettings.wrapper
        .find(".geolocation-map-container")
        .first();

      if (this.container.length !== 1) {
        throw "Geolocation - Map container not found";
      }

      this.initialized = false;
      this.populated = false;
      this.lat = mapSettings.lat;
      this.lng = mapSettings.lng;

      if (typeof mapSettings.id === "undefined") {
        this.id = "map" + Math.floor(Math.random() * 10000);
      } else {
        this.id = mapSettings.id;
      }

      this.mapCenter = mapSettings.map_center;
      this.mapMarkers = this.mapMarkers || [];
      this.mapShapes = this.mapShapes || [];

      return this;
    }

    GeolocationMapBase.prototype = {
      addControl: function (element) {
        // Stub.
      },
      removeControls: function () {
        // Stub.
      },
      getZoom: function () {
        // Stub.
      },
      setZoom: function (zoom, defer) {
        // Stub.
      },
      getCenter: function () {
        // Stub.
      },
      setCenter: function () {
        if (typeof this.wrapper.data("preserve-map-center") !== "undefined") {
          return;
        }

        this.setZoom();
        this.setCenterByCoordinates({ lat: this.lat, lng: this.lng });

        if (typeof this.mapCenter !== "undefined") {
          var that = this;

          var centerOptions = Object
            // .values(this.mapCenter) // Reenable once IE11 is dead. Hopefully soon.
            .keys(that.mapCenter)
            .map(function (item) {
              return that.mapCenter[item];
            }) // IE11 fix from #3046802.
            .sort(function (a, b) {
              return a.weight - b.weight;
            });

          centerOptions.some(
            /**
             * @param {GeolocationCenterOption} centerOption
             */
            function (centerOption) {
              if (
                typeof Drupal.geolocation.mapCenter[
                  centerOption.map_center_id
                ] === "function"
              ) {
                return Drupal.geolocation.mapCenter[centerOption.map_center_id](
                  that,
                  centerOption
                );
              }
            }
          );
        }
      },
      setCenterByCoordinates: function (coordinates, accuracy, identifier) {
        this.centerUpdatedCallback(coordinates, accuracy, identifier);
      },
      setMapMarker: function (marker) {
        this.mapMarkers.push(marker);
        this.markerAddedCallback(marker);
      },
      removeMapMarker: function (marker) {
        var that = this;
        $.each(
          this.mapMarkers,

          /**
           * @param {integer} index - Current index.
           * @param {GeolocationMapMarker} item - Current marker.
           */
          function (index, item) {
            if (item === marker) {
              that.markerRemoveCallback(marker);
              that.mapMarkers.splice(Number(index), 1);
            }
          }
        );
      },
      removeMapMarkers: function () {
        this.mapMarkers.filter(item => typeof item !== 'undefined')
          .forEach(marker => this.removeMapMarker(marker));
      },
      addShape: function (shape) {
        this.mapShapes.push(shape);
      },
      removeShape: function (shape) {
        var that = this;
        $.each(
          this.mapShapes,

          /**
           * @param {integer} index - Current index.
           * @param {GeolocationShape} item - Current shape.
           */
          function (index, item) {
            if (item === shape) {
              that.mapShapes.splice(Number(index), 1);
            }
          }
        );
      },
      removeShapes: function () {
        this.mapShapes.filter(item => typeof item !== 'undefined')
          .forEach(shape => this.removeShape(shape));
      },
      fitMapToMarkers: function (markers, identifier) {
        var boundaries = this.getMarkerBoundaries();
        if (boundaries === false) {
          return false;
        }

        this.fitBoundaries(boundaries, identifier);
      },
      getMarkerBoundaries: function (markers) {
        // Stub.
      },
      fitBoundaries: function (boundaries, identifier) {
        this.centerUpdatedCallback(this.getCenter(), null, identifier);
      },
      clickCallback: function (location) {
        this.clickCallbacks = this.clickCallbacks || [];
        $.each(this.clickCallbacks, function (index, callback) {
          callback(location);
        });
      },
      addClickCallback: function (callback) {
        this.clickCallbacks = this.clickCallbacks || [];
        this.clickCallbacks.push(callback);
      },
      doubleClickCallback: function (location) {
        this.doubleClickCallbacks = this.doubleClickCallbacks || [];
        $.each(this.doubleClickCallbacks, function (index, callback) {
          callback(location);
        });
      },
      addDoubleClickCallback: function (callback) {
        this.doubleClickCallbacks = this.doubleClickCallbacks || [];
        this.doubleClickCallbacks.push(callback);
      },
      contextClickCallback: function (location) {
        this.contextClickCallbacks = this.contextClickCallbacks || [];
        $.each(this.contextClickCallbacks, function (index, callback) {
          callback(location);
        });
      },
      addContextClickCallback: function (callback) {
        this.contextClickCallbacks = this.contextClickCallbacks || [];
        this.contextClickCallbacks.push(callback);
      },
      initializedCallback: function () {
        this.initializedCallbacks = this.initializedCallbacks || [];
        while (this.initializedCallbacks.length > 0) {
          this.initializedCallbacks.shift()(this);
        }
        this.initialized = true;
      },
      addInitializedCallback: function (callback) {
        if (this.initialized) {
          callback(this);
        } else {
          this.initializedCallbacks = this.initializedCallbacks || [];
          this.initializedCallbacks.push(callback);
        }
      },
      updatedCallback: function (mapSettings) {
        var that = this;
        this.updatedCallbacks = this.updatedCallbacks || [];
        this.updatedCallbacks.forEach(function (callback) {
          callback(that, mapSettings);
        });
      },
      addUpdatedCallback: function (callback) {
        this.updatedCallbacks = this.updatedCallbacks || [];
        this.updatedCallbacks.push(callback);
      },
      boundsChangedCallback: function (bounds) {
        this.boundsChangedCallbacks = this.boundsChangedCallbacks || [];
        $.each(this.boundsChangedCallbacks, function (index, callback) {
          callback(bounds);
        });
      },
      addBoundsChangedCallback: function (callback) {
        this.boundsChangedCallbacks = this.boundsChangedCallbacks || [];
        this.boundsChangedCallbacks.push(callback);
      },
      centerUpdatedCallback: function (coordinates, accuracy, identifier) {
        this.centerUpdatedCallbacks = this.centerUpdatedCallbacks || [];
        $.each(this.centerUpdatedCallbacks, function (index, callback) {
          callback(coordinates, accuracy, identifier);
        });
      },
      addCenterUpdatedCallback: function (callback) {
        this.centerUpdatedCallbacks = this.centerUpdatedCallbacks || [];
        this.centerUpdatedCallbacks.push(callback);
      },
      markerAddedCallback: function (marker) {
        this.markerAddedCallbacks = this.markerAddedCallbacks || [];
        $.each(this.markerAddedCallbacks, function (index, callback) {
          callback(marker);
        });
      },
      addMarkerAddedCallback: function (callback, existing) {
        existing = existing || true;
        if (existing) {
          $.each(this.mapMarkers, function (index, marker) {
            callback(marker);
          });
        }
        this.markerAddedCallbacks = this.markerAddedCallbacks || [];
        this.markerAddedCallbacks.push(callback);
      },
      markerRemoveCallback: function (marker) {
        this.markerRemoveCallbacks = this.markerRemoveCallbacks || [];
        $.each(this.markerRemoveCallbacks, function (index, callback) {
          callback(marker);
        });
      },
      addMarkerRemoveCallback: function (callback) {
        this.markerRemoveCallbacks = this.markerRemoveCallbacks || [];
        this.markerRemoveCallbacks.push(callback);
      },
      populatedCallback: function () {
        this.populatedCallbacks = this.populatedCallbacks || [];
        while (this.populatedCallbacks.length > 0) {
          this.populatedCallbacks.shift()(this);
        }
        this.populated = true;
      },
      addPopulatedCallback: function (callback) {
        if (this.populated) {
          callback(this);
        } else {
          this.populatedCallbacks = this.populatedCallbacks || [];
          this.populatedCallbacks.push(callback);
        }
      },
      loadMarkersFromContainer: function () {
        var locations = [];
        this.wrapper
          .find(".geolocation-location")
          .each(function (index, locationWrapperElement) {
            var locationWrapper = $(locationWrapperElement);

            var position = {
              lat: Number(locationWrapper.data("lat")),
              lng: Number(locationWrapper.data("lng")),
            };

            /** @type {GeolocationMapMarker} */
            var location = {
              position: position,
              title: locationWrapper.find(".location-title").text().trim(),
              setMarker: true,
              locationWrapper: locationWrapper,
            };

            if (typeof locationWrapper.data("icon") !== "undefined") {
              location.icon = locationWrapper.data("icon").toString();
            }

            if (typeof locationWrapper.data("label") !== "undefined") {
              location.label = locationWrapper.data("label").toString();
            }

            if (locationWrapper.data("set-marker") === "false") {
              location.setMarker = false;
            }

            locations.push(location);
          });

        return locations;
      },
      loadShapesFromContainer: function () {
        var shapes = [];
        this.wrapper
          .find(".geolocation-shape")
          .each(function (index, shapeWrapperElement) {
            var shapeWrapper = $(shapeWrapperElement);
            var meta = shapeWrapper
              .find('span[typeof="GeoShape"] meta')
              .first();
            if (meta.length === 0) {
              return;
            }

            var type = meta.attr("property").toString();

            var coordinates = [];
            $.each(
              meta.attr("content").toString().split(" "),
              function (index, rawCoordinate) {
                var coordinate = rawCoordinate.split(",");
                if (coordinate[0].length === 0 || coordinate[1].length === 0) {
                  return;
                }
                coordinates.push({
                  lat: parseFloat(coordinate[0]),
                  lng: parseFloat(coordinate[1]),
                });
              }
            );

            /** @type {GeolocationShape} */
            var shape = {
              coordinates: coordinates,
              shape: type,
              shapeWrapper: shapeWrapper,
            };

            switch (type) {
              case "line":
                shape.title = shapeWrapper
                  .find(".polyline-title")
                  .text()
                  .trim();
                break;

              case "polygon":
                shape.title = shapeWrapper.find(".polygon-title").text().trim();

                if (typeof shapeWrapper.data("fillColor") !== "undefined") {
                  shape.fillColor = shapeWrapper.data("fillColor").toString();
                }
                if (typeof shapeWrapper.data("fillOpacity") !== "undefined") {
                  shape.fillOpacity = parseFloat(
                    shapeWrapper.data("fillOpacity").toString()
                  );
                }
                break;
            }

            if (typeof shapeWrapper.data("strokeColor") !== "undefined") {
              shape.strokeColor = shapeWrapper.data("strokeColor").toString();
            }
            if (typeof shapeWrapper.data("strokeWidth") !== "undefined") {
              shape.strokeWidth = parseInt(
                shapeWrapper.data("strokeWidth").toString()
              );
            }
            if (typeof shapeWrapper.data("strokeOpacity") !== "undefined") {
              shape.strokeOpacity = parseFloat(
                shapeWrapper.data("strokeOpacity").toString()
              );
            }

            shapes.push(shape);
          });

        return shapes;
      },
      boundariesNormalized: function (boundaries) {
        if (
          typeof boundaries.north === "number" &&
          typeof boundaries.east === "number" &&
          typeof boundaries.south === "number" &&
          typeof boundaries.west === "number"
        ) {
          return true;
        }

        return false;
      },
      normalizeBoundaries: function (boundaries) {
        var that = this;

        if (that.boundariesNormalized(boundaries)) {
          return boundaries;
        }

        if (
          typeof boundaries.north !== "undefined" &&
          typeof boundaries.south !== "undefined" &&
          typeof boundaries.east !== "undefined" &&
          typeof boundaries.west !== "undefined"
        ) {
          var castBoundaries = {
            north: Number(boundaries.north),
            east: Number(boundaries.east),
            south: Number(boundaries.south),
            west: Number(boundaries.west),
          };

          if (that.boundariesNormalized(castBoundaries)) {
            return castBoundaries;
          }
        }

        $.each(Drupal.geolocation.MapProviders, function (type, name) {
          if (
            typeof Drupal.geolocation[name].prototype.normalizeBoundaries !==
            "undefined"
          ) {
            var normalizedBoundaries = Drupal.geolocation[
              name
            ].prototype.normalizeBoundaries.call(null, boundaries);
          }

          if (that.boundariesNormalized(normalizedBoundaries)) {
            boundaries = normalizedBoundaries;
            return false;
          }
        });

        if (that.boundariesNormalized(boundaries)) {
          return boundaries;
        }

        return false;
      },
    };

    Drupal.geolocation.GeolocationMapBase = GeolocationMapBase;

    /**
     * Factory creating map instances.
     *
     * @constructor
     *
     * @param {GeolocationMapSettings} mapSettings The map settings.
     * @param {Boolean} [reset] Force creation of new map.
     *
     * @return {GeolocationMapInterface|boolean} Un-initialized map.
     */
    function Factory(mapSettings, reset) {
      reset = reset || false;
      mapSettings.type = mapSettings.type || "google_maps";

      var map = null;

      /**
       * Previously stored map.
       * @type {boolean|GeolocationMapInterface}
       */
      var existingMap = Drupal.geolocation.getMapById(mapSettings.id);

      if (reset === true || !existingMap) {
        if (
          typeof Drupal.geolocation[
            Drupal.geolocation.MapProviders[mapSettings.type]
          ] !== "undefined"
        ) {
          var mapProvider =
            Drupal.geolocation[
              Drupal.geolocation.MapProviders[mapSettings.type]
            ];
          map = new mapProvider(mapSettings);
          Drupal.geolocation.maps.push(map);
        }
      } else {
        map = existingMap;
        map.updatedCallback(mapSettings);
      }

      if (!map) {
        console.error("Map could not be initialized."); // eslint-disable-line no-console .
        return false;
      }

      if (typeof map.container === "undefined") {
        console.error("Map container not set."); // eslint-disable-line no-console .
        return false;
      }

      if (map.container.length !== 1) {
        console.error("Map container not unique."); // eslint-disable-line no-console .
        return false;
      }

      return map;
    }

    Drupal.geolocation.Factory = Factory;

    /**
     * @type {Object}
     */
    Drupal.geolocation.MapProviders = {};

    Drupal.geolocation.addMapProvider = function (type, name) {
      Drupal.geolocation.MapProviders[type] = name;
    };

    /**
     * Get map by ID.
     *
     * @param {String} id - Map ID to retrieve.
     *
     * @return {GeolocationMapInterface|boolean} - Retrieved map or false.
     */
    Drupal.geolocation.getMapById = function (id) {
      var map = false;
      $.each(Drupal.geolocation.maps, function (index, currentMap) {
        if (currentMap.id === id) {
          map = currentMap;
        }
      });

      if (!map) {
        return false;
      }

      if (typeof map.container === "undefined") {
        console.error("Existing map container not set."); // eslint-disable-line no-console .
        return false;
      }

      if (map.container.length !== 1) {
        console.error("Existing map container not unique."); // eslint-disable-line no-console .
        return false;
      }

      return map;
    };

    /**
     * @typedef {Object} GeolocationMapFeatureSettings
     *
     * @property {String} id
     * @property {boolean} enabled
     * @property {boolean} executed
     */

    /**
     * Callback when map is clicked.
     *
     * @callback GeolocationMapFeatureCallback
     *
     * @param {GeolocationMapInterface} map - Map.
     * @param {GeolocationMapFeatureSettings} featureSettings - Settings.
     *
     * @return {boolean} - Executed successfully.
     */

    /**
     * Get map by ID.
     *
     * @param {String} featureId - Map ID to retrieve.
     * @param {GeolocationMapFeatureCallback} callback - Retrieved map or false.
     * @param {Object} drupalSettings - Drupal settings.
     */
    Drupal.geolocation.executeFeatureOnAllMaps = function (
      featureId,
      callback,
      drupalSettings
    ) {
      if (typeof drupalSettings.geolocation === "undefined") {
        return false;
      }

      $.each(
        drupalSettings.geolocation.maps,

        /**
         * @param {String} mapId - ID of current map
         * @param {Object} mapSettings - settings for current map
         * @param {GeolocationMapFeatureSettings} mapSettings[featureId] - Feature settings for current map
         */
        function (mapId, mapSettings) {
          if (typeof mapSettings[featureId] === "undefined") {
            return;
          }
          if (!mapSettings[featureId].enable) {
            return;
          }
          var map = Drupal.geolocation.getMapById(mapId);
          if (!map) {
            return;
          }

          map.features = map.features || {};
          map.features[featureId] = map.features[featureId] || {};
          if (typeof map.features[featureId].executed === "undefined") {
            map.features[featureId].executed = false;
          }

          if (map.features[featureId].executed) {
            return;
          }

          map.addPopulatedCallback(function (map) {
            if (map.features[featureId].executed) {
              return;
            }
            var result = callback(map, mapSettings[featureId]);

            if (result === true) {
              map.features[featureId].executed = true;
            }
          });
        }
      );
    };
  }
)(jQuery, Drupal);
