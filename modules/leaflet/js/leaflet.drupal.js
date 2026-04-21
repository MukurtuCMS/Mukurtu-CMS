(function($, Drupal, drupalSettings, once) {

  "use strict";

  Drupal.behaviors.leaflet = {
    attach: function(context, settings) {

      // For each Leaflet Map/id defined process with Leaflet Map and Features
      // generation.
      $.each(settings.leaflet, function(mapid, leaflet_settings) {

        // Ensure the Leaflet Behavior is attached only once to each Leaflet map
        // id element.
        // @see https://www.drupal.org/project/leaflet/issues/3314762#comment-15044223
        once('behaviour-leaflet', '#' + mapid).forEach(function (element) {
          const map_container = $(element);

          // Function to load the Leaflet Map, based on the provided mapid.
          function loadMap(mapid) {
            // Process a new Leaflet Map only if the map container is empty.
            // Avoid reprocessing a Leaflet Map already initialised.
            if (map_container.data('leaflet') === undefined) {
              map_container.data('leaflet', new Drupal.Leaflet(L.DomUtil.get(mapid), mapid, leaflet_settings.map));
              if (leaflet_settings.features.length > 0) {
                // Add Leaflet Map Features.
                map_container.data('leaflet').add_features(leaflet_settings.features, true);
              }

              // Add the Leaflet map to data settings object to make it
              // accessible and extendable from other Drupal.behaviors attached
              // functions.
              // @NOTE: i.e. this is used by the leaflet.widget.js.
              leaflet_settings.lMap = map_container.data('leaflet').lMap;

              // Add the Leaflet Map Markers to data settings object to make it
              // also accessible and extendable.
              leaflet_settings.markers = map_container.data('leaflet').markers;

              // Set initial Map position to wrap its defined bounds.
              map_container.data('leaflet').fitBounds();

              // Set the start center and the start zoom.
              if (!map_container.data('leaflet').start_center && !map_container.data('leaflet').start_zoom) {
                map_container.data('leaflet').start_center = map_container.data('leaflet').lMap.getCenter();
                map_container.data('leaflet').start_zoom = map_container.data('leaflet').lMap.getZoom();
              }

              // Define the global Drupal.Leaflet[mapid] object to be accessible
              // from outside - NOTE: This should always be created after setting
              // the (above) start center.
              Drupal.Leaflet[mapid] = map_container.data('leaflet');

              // Add the Map Geocoder Control if requested.
              if (!Drupal.Leaflet[mapid].geocoder_control && Drupal.Leaflet.prototype.map_geocoder_control) {
                const mapGeocoderControlDiv = document.createElement('div');
                Drupal.Leaflet[mapid].geocoder_control = Drupal.Leaflet.prototype.map_geocoder_control(mapGeocoderControlDiv, mapid);
                Drupal.Leaflet[mapid].geocoder_control.addTo(Drupal.Leaflet[mapid].lMap);
                const geocoder_settings = drupalSettings.leaflet[mapid].map.settings.geocoder.settings;
                Drupal.Leaflet.prototype.map_geocoder_control.autocomplete(mapid, geocoder_settings);
              }

              // Add the Layers Control, if initialised/existing.
              if (Drupal.Leaflet[mapid].layer_control) {
                Drupal.Leaflet[mapid].lMap.addControl(Drupal.Leaflet[mapid].layer_control);
              }

              // Add and Initialise the Map Reset View Control if requested.
              if (!Drupal.Leaflet[mapid].reset_view_control &&
                  map_container.data('leaflet').map_settings.reset_map &&
                  map_container.data('leaflet').map_settings.reset_map.control) {
                const map_reset_view_options = map_container.data('leaflet').map_settings.reset_map.options ?
                  JSON.parse(map_container.data('leaflet').map_settings.reset_map.options) : {};
                map_reset_view_options.latlng = map_container.data('leaflet').start_center;
                map_reset_view_options.zoom = map_container.data('leaflet').start_zoom;
                Drupal.Leaflet[mapid].reset_view_control = L.control.resetView(map_reset_view_options)
                  .addTo(map_container.data('leaflet').lMap);
              }

              // Add and Initialise the Map Scale Control if requested.
              if (!Drupal.Leaflet[mapid].map_scale_control &&
                  map_container.data('leaflet').map_settings.map_scale &&
                  map_container.data('leaflet').map_settings.map_scale.control) {
                const map_scale_options = map_container.data('leaflet').map_settings.map_scale.options ?
                  JSON.parse(map_container.data('leaflet').map_settings.map_scale.options) : {};
                Drupal.Leaflet[mapid].map_scale_control = L.control.scale(map_scale_options)
                  .addTo(map_container.data('leaflet').lMap);
              }

              // Add the Locate Control if requested.
              if (!Drupal.Leaflet[mapid].locate_control &&
                  map_container.data('leaflet').map_settings.locate &&
                  map_container.data('leaflet').map_settings.locate.control) {
                const locate_options = map_container.data('leaflet').map_settings.locate.options ?
                  JSON.parse(map_container.data('leaflet').map_settings.locate.options) : {};
                Drupal.Leaflet[mapid].locate_control = L.control.locate(locate_options)
                  .addTo(map_container.data('leaflet').lMap);

                // In case this Leaflet Map is not in a Widget Context, eventually perform the Automatic User Locate, if requested.
                if (!leaflet_settings.hasOwnProperty('leaflet_widget') &&
                    map_container.data('leaflet').map_settings.hasOwnProperty('locate') &&
                    map_container.data('leaflet').map_settings.locate.automatic) {
                  Drupal.Leaflet[mapid].locate_control.start();
                }
              }

              // Add Fullscreen Control, if requested.
              if (!Drupal.Leaflet[mapid].fullscreen_control &&
                  map_container.data('leaflet').map_settings.fullscreen &&
                  map_container.data('leaflet').map_settings.fullscreen.control) {
                const map_fullscreen_options = map_container.data('leaflet').map_settings.fullscreen.options ?
                  JSON.parse(map_container.data('leaflet').map_settings.fullscreen.options) : {};
                Drupal.Leaflet[mapid].fullscreen_control = L.control.fullscreen(map_fullscreen_options)
                  .addTo(map_container.data('leaflet').lMap);
              }

              // Attach Leaflet Map listeners On Popup Open.
              leaflet_settings.lMap.on('popupopen', function(e) {
                // On leaflet-ajax-popup selector, fetch and set Ajax content.
                const element = e.popup._contentNode;
                const content = $('*[data-leaflet-ajax-popup]', element);
                if (content.length) {
                  const url = content.data('leaflet-ajax-popup');
                  Drupal.ajax({url: url}).execute()
                    .done(function(data) {
                      // Copy the html we received via AJAX to the popup, so we won't
                      // have to make another AJAX call (#see 3258780).
                      e.popup.setContent(data[0].data);

                      // Attach drupal behaviors on new content.
                      Drupal.attachBehaviors(element, drupalSettings);
                    })
                    .fail(function() {
                      // In case of failing fetching data.
                      e.popup.close();
                    });
                }

                // Make the (eventually present) Tooltip disappear on Popup Open
                // in case the Popup is generated from a _source.
                if (e.popup._source) {
                  const tooltip = e.popup._source.getTooltip();
                  // not all features will have tooltips!
                  if (tooltip) {
                    // use opacity to make the tooltip disappear.
                    tooltip.setOpacity(0);
                  }
                }
              });

              // Attach Leaflet Map listeners On Popup Close.
              leaflet_settings.lMap.on('popupclose', function(e) {
                // Make the (eventually present) Tooltip re-appear on Popup Close.
                // in case the Popup is generated from a _source.
                if (e.popup._source) {
                  const tooltip = e.popup._source.getTooltip();
                  if (tooltip) {
                    tooltip.setOpacity(0.9);
                  }
                }
              });

              // Define and Trigger 'leafletMapInit' event to let other modules
              // and custom js libraries to bind & rect on Leaflet Map
              // generation and extend & interact with its definition,
              // properties and contents / features.
              // NOTE: don't change this trigger arguments,
              // to preserve backwards compatibility.
              $(document).trigger('leafletMapInit', [leaflet_settings.map, leaflet_settings.lMap, mapid, leaflet_settings.markers]);
              // NOTE: Keep also this pre-existing event for backwards
              // compatibility with Leaflet < 2.1.0.
              $(document).trigger('leaflet.map', [leaflet_settings.map, leaflet_settings.lMap, mapid, leaflet_settings.markers]);
            }
          }

          // If the IntersectionObserver API is available, create an observer to load the map when it enters the viewport
          // It will be used to handle map loading instead of displaying the map on page load.
          let mapObserver = null;
          if ('IntersectionObserver' in window) {
            mapObserver = new IntersectionObserver(function(entries, observer) {
              for (let i = 0; i < entries.length; i++) {
                if (entries[i].isIntersecting) {
                  const mapid = entries[i].target.id;
                  loadMap(mapid);
                }
              }
            });
          }

          // Load the Leaflet Map, lazy based on the mapObserver, or not.
          if (mapObserver && leaflet_settings.map.settings.map_lazy_load?.lazy_load) {
            mapObserver.observe(element);
          } else {
            loadMap(mapid);
          }
        });
      });
    }
  };

  /**
   * Define a main Drupal.Leaflet function being generated/triggered on each
   * Leaflet Map map_container element load.
   *
   * @param map_container
   *   The Leaflet Map map_container.
   * @param mapid
   *   The Leaflet Map id.
   * @param map_definition
   *   The Leaflet Map definition.
   * @constructor
   */
  Drupal.Leaflet = function(map_container, mapid, map_definition) {
    this.mapid = mapid;
    this.map_definition = map_definition;
    this.map_settings = this.map_definition.settings;
    this.bounds = [];
    this.base_layers = {};
    this.overlays = {};
    this.lMap = null;
    this.start_center = null;
    this.start_zoom = null;
    this.layer_control = null;
    this.markers = {};
    this.features = {};
    this.initialise(mapid);
  };

  /**
   * Initialise the specific Leaflet Map
   *
   * @param mapid
   *   The dom element #id to inject the Leaflet Map into.
   */
  Drupal.Leaflet.prototype.initialise = function(mapid) {
    // Instantiate a new Leaflet map.
    this.lMap = new L.Map(mapid, this.map_settings);

    // Add map layers (base and overlay layers).
    let i = 0;
    for (const key in this.map_definition.layers) {
      const layer = this.map_definition.layers[key];
      // Distinguish between "base" and "overlay" layers.
      // Default to "base" in case "layer_type" has not been defined in hook_leaflet_map_info().
      layer.layer_type = (typeof layer.layer_type === 'undefined') ? 'base' : layer.layer_type;

      switch (layer.layer_type) {
        case 'overlay':
          const overlay_layer = this.create_layer(layer, key);
          const layer_hidden = (typeof layer.layer_hidden === "undefined") ? false : layer.layer_hidden;
          this.add_overlay(key, overlay_layer, layer_hidden);
          break;

        default:
          this.add_base_layer(key, layer, i);
          // Only the first base layer needs to be added to the map - all the
          // others are accessed via the layer switcher.
          if (i === 0) {
            i++;
          }
          break;
      }
      i++;
    }

    // Set initial view, fallback to displaying the whole world.
    if (this.map_settings.center && this.map_settings.zoom) {
      this.lMap.setView(
        new L.LatLng(this.map_settings.center.lat, this.map_settings.center.lon),
        this.map_settings.zoom
      );
    }
    else {
      this.lMap.fitWorld();
    }

    // Set the position of the Zoom Control, if enabled.
    if (this.lMap.zoomControl) {
      this.lMap.zoomControl.setPosition(this.map_settings.zoomControlPosition);
    }

    // Set to refresh when first in viewport to avoid missing visibility.
    new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.intersectionRatio > 0) {
          this.lMap.invalidateSize();
          observer.disconnect();
        }
      });
    }).observe(this.lMap._container);
  };

  /**
   * Initialise the Leaflet Map Layers (Overlays) Control
   */
  Drupal.Leaflet.prototype.initialise_layers_control = function() {
    const count_layers = function(obj) {
      // Browser compatibility: Chrome, IE 9+, FF 4+, or Safari 5+.
      // @see http://kangax.github.com/es5-compat-table/
      return Object.keys(obj).length;
    };

    // Only add a layer switcher if it is enabled in settings, and we have
    // at least two base layers or at least one overlay.
    if (this.layer_control == null &&
        ((this.map_settings.layerControl && count_layers(this.base_layers) > 1) ||
         count_layers(this.overlays) > 0)) {
      const base_layers = count_layers(this.base_layers) > 1 ? this.base_layers : [];
      // Instantiate layer control, using settings.layerControl as settings.
      this.layer_control = new L.Control.Layers(
        base_layers,
        [],
        this.map_settings.layerControlOptions
      );
    }
  };

  /**
   * Create & Add a Base Layer to the Leaflet Map and Layers Control.
   *
   * @param key
   *   The Layer index/label.
   * @param definition
   *   The Layer definition,
   * @param i
   *   The layers progressive counter.
   */
  Drupal.Leaflet.prototype.add_base_layer = function(key, definition, i) {
    const base_layer = this.create_layer(definition, key);
    this.base_layers[key] = base_layer;

    // Only the first base layer needs to be added to the map - all the others are accessed via the layer switcher.
    if (i === 0) {
      this.lMap.addLayer(base_layer);
    }

    // Initialise the Layers Control, if not yet.
    if (this.layer_control == null) {
      this.initialise_layers_control();
    } else {
      // Add the new base layer to layer_control.
      this.layer_control.addBaseLayer(base_layer, key);
    }
  };

  /**
   * Adds a Specific Layer and related Overlay to the Leaflet Map.
   *
   * @param {string} label
   *   The Overlay Layer Label.
   * @param layer
   *   The Leaflet Overlay.
   * @param {boolean} hidden_layer
   *   The flag to disable the Layer from the Over Layers Control.
   */
  Drupal.Leaflet.prototype.add_overlay = function(label, layer, hidden_layer) {
    if (!hidden_layer) {
      this.lMap.addLayer(layer);
    }

    // Add the Overlay to the Drupal.Leaflet object.
    this.overlays[label] = layer;

    // Initialise the Layers Control, if it is not.
    if (label && this.layer_control == null) {
      this.initialise_layers_control();
    }

    // Add the Overlay to the Layer Control only if there is a Label.
    if (label && this.layer_control) {
      // If we already have a layer control, add the new overlay to it.
      this.layer_control.addOverlay(layer, label);
    }
  };

  /**
   * Add Leaflet Features to the Leaflet Map.
   *
   * @param {Array} features
   *   Features List definition.
   * @param {boolean} initial
   *   Boolean to identify initial status.
   */
  Drupal.Leaflet.prototype.add_features = function(features, initial) {
    // Define Map Layers holder.
    const layers = {};

    for (let i = 0; i < features.length; i++) {
      const feature = features[i];
      let lFeature;

      // In case of a Features Group.
      if (feature.group) {
        // Define a named Layer Group
        layers[feature.group_label] = this.create_feature_group();
        for (const groupKey in feature.features) {
          const groupFeature = feature.features[groupKey];
          lFeature = this.create_feature(groupFeature);
          if (lFeature !== undefined) {
            // Add the lFeature to the lGroup.
            layers[feature.group_label].addLayer(lFeature);

            // Allow others to do something with the feature that was just added to the map.
            $(document).trigger('leaflet.feature', [lFeature, groupFeature, this, layers]);
          }
        }

        // Add the group to the layer switcher.
        this.add_overlay(feature.group_label, layers[feature.group_label], feature.disabled);
      }
      else {
        lFeature = this.create_feature(feature);
        if (lFeature !== undefined) {
          // Add the Leaflet Feature to the Map.
          this.lMap.addLayer(lFeature);

          // Allow others to do something with the feature that was just added to the map.
          $(document).trigger('leaflet.feature', [lFeature, feature, this]);
        }
      }
    }

    // Allow plugins to do things after features have been added.
    $(document).trigger('leaflet.features', [initial || false, this]);
  };

  /**
   * Create a Leaflet Feature Group.
   *
   * @returns {Object}
   *   A Leaflet feature group.
   */
  Drupal.Leaflet.prototype.create_feature_group = function() {
    return new L.featureGroup();
  };

  /**
   * Add Leaflet Popup to the Leaflet Feature.
   *
   * @param {Object} lFeature
   *   The Leaflet Feature.
   * @param {Object} feature
   *   The Feature coming from Drupal settings.
   */
  Drupal.Leaflet.prototype.feature_bind_popup = function(lFeature, feature) {
    // Attach the Popup only if supported and a value is set for it.
    if (typeof lFeature.bindPopup !== "undefined" && feature.popup && feature.popup.value) {
      const popup_options = feature.popup.options ? JSON.parse(feature.popup.options) : {};
      lFeature.bindPopup(feature.popup.value, popup_options);
    }
  };

  /**
   * Add Leaflet Tooltip to the Leaflet Feature.
   *
   * @param {Object} lFeature
   *   The Leaflet Feature.
   * @param {Object} feature
   *   The Feature coming from Drupal settings.
   */
  Drupal.Leaflet.prototype.feature_bind_tooltip = function(lFeature, feature) {
    // Set the Leaflet Tooltip, with its options (if the stripped value is not null).
    if (feature.tooltip && feature.tooltip.value.replace(/(<([^>]+)>)/gi, "").trim().length > 0) {
      const tooltip_options = feature.tooltip.options ? JSON.parse(feature.tooltip.options) : {};

      // Cleanup tooltip className from commas, to support multivalue class fields.
      if (tooltip_options.hasOwnProperty('className') && tooltip_options.className.length > 0) {
        tooltip_options.className = tooltip_options.className.replaceAll(",", "");
      }

      // Need to more correctly set the tooltip_options.permanent option.
      tooltip_options.permanent = tooltip_options.permanent === true || tooltip_options.permanent === "true";

      lFeature.bindTooltip(feature.tooltip.value, tooltip_options);
    }
  };

  /**
   * Set Leaflet Feature path style.
   *
   * @param {Object} lFeature
   *   The Leaflet Feature.
   * @param {Object} feature
   *   The Feature coming from Drupal settings.
   */
  Drupal.Leaflet.prototype.set_feature_path_style = function(lFeature, feature) {
    let lFeature_path_style;
    try {
      lFeature_path_style = feature.path ?
        (feature.path instanceof Object ? feature.path : JSON.parse(feature.path)) : {};
    }
    catch (e) {
      lFeature_path_style = {};
    }

    // Make sure that the weight property is cast into integer, for avoiding
    // polygons eventually disappearing with pan and zooming.
    // @see: https://stackoverflow.com/a/65892728/5451394
    if (lFeature_path_style.hasOwnProperty('weight')) {
      lFeature_path_style.weight = parseInt(lFeature_path_style.weight);
    }
    lFeature.setStyle(lFeature_path_style);
  };

  /**
   * Extend Map Bounds with new lFeature/feature.
   *
   * @param {Object} lFeature
   *   The Leaflet Feature.
   * @param {Object} feature
   *   The Feature coming from Drupal settings.
   *   (this parameter should be kept to eventually extend this method with
   *   conditional logics on feature properties)
   */
  Drupal.Leaflet.prototype.extend_map_bounds = function(lFeature, feature) {
    if (feature.type === 'point') {
      this.bounds.push([feature.lat, feature.lon]);
    } else if (lFeature.getBounds) {
      this.bounds.push(lFeature.getBounds().getSouthWest(), lFeature.getBounds().getNorthEast());
    }
  };

  /**
   * Add Marker and Feature to the Drupal.Leaflet object.
   *
   * @param {Object} lFeature
   *   The Leaflet Feature.
   * @param {Object} feature
   *   The Feature coming from Drupal settings.
   */
  Drupal.Leaflet.prototype.push_markers_features = function(lFeature, feature) {
    if (feature.entity_id) {
      // Generate the markers object index based on entity id (and geofield
      // cardinality), and add the marker to the markers object.
      const entity_id = feature.entity_id;
      if (this.map_definition.geofield_cardinality && this.map_definition.geofield_cardinality !== 1) {
        let i = 0;
        while (this.markers[entity_id + '-' + i]) {
          i++;
        }
        this.markers[entity_id + '-' + i] = lFeature;
        this.features[entity_id + '-' + i] = feature;
      }
      else {
        this.markers[entity_id] = lFeature;
        this.features[entity_id] = feature;
      }
    }
  };

  /**
   * Generates a Leaflet Geometry (Point or Geometry).
   *
   * @param {Object} feature
   *   The feature definition coming from Drupal backend.
   * @param {Object|boolean} map_settings
   *   The map_settings if defined, false otherwise.
   *
   * @returns {Object}
   *   The generated Leaflet Geometry.
   */
  Drupal.Leaflet.prototype.create_geometry = function(feature, map_settings = false) {
    let lFeature;
    switch (feature.type) {
      case 'point':
        lFeature = this.create_point(feature);
        break;

      case 'linestring':
        lFeature = this.create_linestring(
          feature,
          map_settings?.leaflet_markercluster.include_path ?? false
        );
        break;

      case 'polygon':
        lFeature = this.create_polygon(
          feature,
          map_settings?.leaflet_markercluster.include_path ?? false
        );
        break;

      case 'multipolygon':
        lFeature = this.create_multipolygon(
          feature,
          map_settings?.leaflet_markercluster.include_path ?? false
        );
        break;

      case 'multipolyline':
        lFeature = this.create_multipoly(
          feature,
          map_settings?.leaflet_markercluster.include_path ?? false
        );
        break;

      // In case of singular cases where feature.type is json we use this.create_json method.
      // @see https://www.drupal.org/project/leaflet/issues/3377403
      // @see https://www.drupal.org/project/leaflet/issues/3186029
      case 'json':
        lFeature = this.create_json(feature.json, feature.options, feature.events);
        break;

      case 'multipoint':
      case 'geometrycollection':
        lFeature = this.create_collection(feature);
        break;

      default:
        lFeature = {};
    }
    return lFeature;
  };

  /**
   * Generates a Leaflet Feature (Point or Geometry)
   * with Leaflet adds on (Tooltip, Popup, Path Styles, etc.)
   *
   * @param {Object} feature
   *   The feature definition coming from Drupal backend.
   * @returns {Object}
   *   The generated Leaflet Feature.
   */
  Drupal.Leaflet.prototype.create_feature = function(feature) {
    const map_settings = this.map_settings ?? null;
    const lFeature = this.create_geometry(feature, map_settings);

    // Eventually add Tooltip to the lFeature.
    this.feature_bind_tooltip(lFeature, feature);

    // Eventually add Popup to the lFeature.
    this.feature_bind_popup(lFeature, feature);

    // Eventually Set Style for Path/Geometry lFeature.
    if (lFeature.setStyle) {
      this.set_feature_path_style(lFeature, feature);
    }

    // Eventually extend Map Bounds with new lFeature.
    this.extend_map_bounds(lFeature, feature);

    // Add Marker and Feature to the Drupal.Leaflet object.
    this.push_markers_features(lFeature, feature);

    return lFeature;
  };

  /**
   * Generate a Leaflet Layer.
   *
   * @param {Object} layer
   *   The Layer definition.
   * @param {string} key
   *   The Layer index/label.
   *
   * @returns {Object}
   *   The Leaflet layer object.
   */
  Drupal.Leaflet.prototype.create_layer = function(layer, key) {
    const self = this;
    let map_layer;
    const layer_type = layer.type ?? 'base';
    const urlTemplate = layer.urlTemplate ?? '';
    const layer_options = layer.options ?? {};

    switch (layer_type) {
      case 'wms':
        map_layer = new L.tileLayer.wms(urlTemplate, layer_options);
        break;

      case 'vector':
        map_layer = new L.maplibreGL({
          style: urlTemplate,
          attribution: layer_options.attribution ?? '',
          pitch: layer_options.pitch ?? '',
          bearing: layer_options.bearing ?? ''
        });
        break;

      default:
        map_layer = new L.tileLayer(urlTemplate, layer_options);
    }

    map_layer._leaflet_id = key;

    // Layers served from TileStream need this correction in the y coordinates.
    // TODO: Need to explore this more and find a more elegant solution.
    if (layer.type === 'tilestream') {
      map_layer.getTileUrl = function(tilePoint) {
        self._adjustTilePoint(tilePoint);
        const zoom = self._getZoomForUrl();
        return L.Util.template(self._url, L.Util.extend({
          s: self._getSubdomain(tilePoint),
          z: zoom,
          x: tilePoint.x,
          y: Math.pow(2, zoom) - tilePoint.y - 1
        }, self.options));
      };
    }
    return map_layer;
  };

  /**
   * Leaflet Icon creator.
   *
   * @param {Object} options
   *   The Icon options.
   *
   * @returns {Object}
   *   Leaflet Icon object.
   */
  Drupal.Leaflet.prototype.create_icon = function(options) {
    const icon_options = {
      iconUrl: options.iconUrl,
    };

    // Apply Icon properties
    // @see https://leafletjs.com/reference.html#icon

    // Icon Size.
    if (options.iconSize) {
      icon_options.iconSize = new L.Point(
        parseInt(options.iconSize.x),
        parseInt(options.iconSize.y)
      );
    }

    // Icon Anchor.
    if (options.iconAnchor && options.iconAnchor.x && options.iconAnchor.y) {
      icon_options.iconAnchor = new L.Point(
        parseInt(options.iconAnchor.x),
        parseInt(options.iconAnchor.y)
      );
    }

    // Popup Anchor.
    if (options.popupAnchor && options.popupAnchor.x && options.popupAnchor.y) {
      icon_options.popupAnchor = new L.Point(
        parseInt(options.popupAnchor.x),
        parseInt(options.popupAnchor.y)
      );
    }

    // Popup ShadowUrl.
    if (options.shadowUrl) {
      icon_options.shadowUrl = options.shadowUrl;
    }

    // Popup ShadowSize.
    if (options.shadowSize && options.shadowSize.x && options.shadowSize.y) {
      icon_options.shadowSize = new L.Point(
        parseInt(options.shadowSize.x),
        parseInt(options.shadowSize.y)
      );
    }

    // Popup ShadowAnchor.
    if (options.shadowAnchor && options.shadowAnchor.x && options.shadowAnchor.y) {
      icon_options.shadowAnchor = new L.Point(
        parseInt(options.shadowAnchor.x),
        parseInt(options.shadowAnchor.y)
      );
    }

    if (options.className) {
      icon_options.className = options.className;
    }

    // Popup IconRetinaUrl.
    // @see https://www.drupal.org/project/leaflet/issues/3268023
    if (options.iconRetinaUrl) {
      icon_options.iconRetinaUrl = options.iconRetinaUrl;
    }

    return new L.Icon(icon_options);
  };

  /**
   * Leaflet DIV Icon creator.
   *
   * @param options
   *   The Icon options.
   *
   * @returns {*}
   */
  Drupal.Leaflet.prototype.create_divicon = function (options) {
    let html_class = options['html_class'] || '';
    let icon = new L.DivIcon({html: options.html, className: html_class});

    // Apply Icon properties
    // @see https://leafletjs.com/reference.html#icon

    // Icon Size.
    if (options.iconSize) {
      icon.options.iconSize = new L.Point(parseInt(options.iconSize.x, 10), parseInt(options.iconSize.y, 10));
    }
    // Icon Anchor.
    if (options.iconAnchor && options.iconAnchor.x && options.iconAnchor.y) {
      icon.options.iconAnchor = new L.Point(parseInt(options.iconAnchor.x), parseInt(options.iconAnchor.y));
    }
    // Popup Anchor.
    if (options.popupAnchor && options.popupAnchor.x && options.popupAnchor.y) {
      icon.options.popupAnchor = new L.Point(parseInt(options.popupAnchor.x), parseInt(options.popupAnchor.y));
    }

    return icon;
  };

  /**
   * Return Geometry Construction Base Options.
   *
   * @param feature
   *   The feature definition.
   *
   * @returns {*}
   */
  Drupal.Leaflet.prototype.create_geometry_base_options = function(feature) {
    // Assign the marker title value depending if a Marker simple title or a
    // Leaflet tooltip was set.
    let marker_title = '';
    if (feature.title) {
      marker_title = feature.title.replace(/<[^>]*>/g, '').trim()
    }
    else if (feature.tooltip && feature.tooltip.value) {
      marker_title = feature.tooltip.value.replace(/<[^>]*>/g, '').trim();
    }
    return {
      title: marker_title ?? "",
      className: feature.icon && feature.icon.className ? feature.icon.className.replaceAll(",", "") : '',
      alt: marker_title ?? "",
      group_label: feature.group_label ?? '',
    };
  }

  /**
   * Leaflet Point (Marker) creator.
   *
   * @param feature
   *   The feature definition.
   *
   * @returns {*}
   */
  Drupal.Leaflet.prototype.create_point = function(feature) {

    const latLng = new L.LatLng(feature.lat, feature.lon);
    let options = this.create_geometry_base_options(feature);
    let lMarker = new L.Marker(latLng, options);

    if (feature.icon) {
      if (feature.icon.iconType && feature.icon.iconType === 'html' && feature.icon.html) {
        let icon = this.create_divicon(feature.icon);
        lMarker.setIcon(icon);
      }
      else if (feature.icon.iconType && feature.icon.iconType === 'circle_marker') {
        try {
          // Extend the options with circle marker specific properties,
          Object.assign(options , feature.icon.circle_marker_options ? JSON.parse(feature.icon.circle_marker_options) : {})
          options.radius = options.radius ? parseInt(options['radius']) : 10;
        }
        catch (e) {
          options = {};
        }
        lMarker = new L.CircleMarker(latLng, options);
      }
      else if (feature.icon.iconUrl) {
        feature.icon.iconSize = feature.icon.iconSize || {};
        feature.icon.iconSize.x = feature.icon.iconSize.x || this.naturalWidth;
        feature.icon.iconSize.y = feature.icon.iconSize.y || this.naturalHeight;
        if (feature.icon.shadowUrl) {
          feature.icon.shadowSize = feature.icon.shadowSize || {};
          feature.icon.shadowSize.x = feature.icon.shadowSize.x || this.naturalWidth;
          feature.icon.shadowSize.y = feature.icon.shadowSize.y || this.naturalHeight;
        }
        let icon = this.create_icon(feature.icon);
        lMarker.setIcon(icon);
      }
    }

    return lMarker;
  };

  /**
   * Leaflet Linestring creator.
   *
   * @param polyline
   *   The Polyline definition.
   * @param clusterable
   *   Clusterable bool option.
   *
   * @returns {*}
   */
  Drupal.Leaflet.prototype.create_linestring = function(polyline, clusterable = false) {
    let latlngs = [];
    for (let i = 0; i < polyline.points.length; i++) {
      let latlng = new L.LatLng(polyline.points[i].lat, polyline.points[i].lon);
      latlngs.push(latlng);
    }
    const options = this.create_geometry_base_options(polyline);
    return clusterable ? new L.PolylineClusterable(latlngs, options) : new L.Polyline(latlngs, options);
  };

  /**
   * Leaflet Polygon creator.
   *
   * @param polygon
   *   The polygon definition,
   * @param clusterable
   *   Clusterable bool option.
   *
   * @returns {*}
   */
  Drupal.Leaflet.prototype.create_polygon = function(polygon, clusterable = false) {
    const coordinates = polygon.points ?? [];
    const options = this.create_geometry_base_options(polygon);
    return clusterable ? new L.PolygonClusterable(coordinates, options) : new L.Polygon(coordinates, options);
  };

  /**
   * Leaflet Multi-Polygon creator.
   *
   * @param multipolygon
   *   The polygon definition,
   * @param clusterable
   *   Clusterable bool option.
   *
   * @returns {*}
   */
  Drupal.Leaflet.prototype.create_multipolygon = function(multipolygon, clusterable = false) {
    const coordinates = multipolygon.points ?? [];
    const options = this.create_geometry_base_options(multipolygon);
    return clusterable ? new L.PolygonClusterable(coordinates, options) : new L.Polygon(coordinates, options);
  };

  /**
   * Leaflet Multi-Poly creator (both Polygons & Poly-lines)
   *
   * @param multipoly
   *   The multipoly definition,
   * @param clusterable
   *   Clusterable bool option.
   *
   * @returns {*}
   */
  Drupal.Leaflet.prototype.create_multipoly = function(multipoly, clusterable = false) {
    let polygons = [];
    for (let x = 0; x < multipoly.component.length; x++) {
      let latlngs = [];
      let polygon = multipoly.component[x];
      for (let i = 0; i < polygon.points.length; i++) {
        let latlng = new L.LatLng(polygon.points[i].lat, polygon.points[i].lon);
        latlngs.push(latlng);
      }
      polygons.push(latlngs);
    }
    const options = this.create_geometry_base_options(multipoly);
    if (multipoly.multipolyline) {
      return clusterable ? new L.PolylineClusterable(polygons, options) : new L.Polyline(polygons, options);
    }
    else {
      return clusterable ? new L.PolygonClusterable(polygons, options) : new L.Polygon(polygons, options);
    }
  };

  /**
   *  Leaflet Collection creator.
   *
   * @param collection
   *   The collection definition.
   *
   * @returns {*}
   */
  Drupal.Leaflet.prototype.create_collection = function(collection) {
    let layers = new L.featureGroup();
    for (let x = 0; x < collection.component.length; x++) {
      let feature = { ...collection, ...collection.component[x]};
      layers.addLayer(this.create_feature(feature));
    }
    return layers;
  };

  /**
   * Leaflet Geo JSON Creator.
   *
   * In case of singular cases where feature type is json we use this.create_json method.
   * @see https://www.drupal.org/project/leaflet/issues/3377403
   * @see https://www.drupal.org/project/leaflet/issues/3186029
   *
   * @param json
   *   The json input.
   * @param options
   *   The options array,
   *   that would reflect the GeoJSON Leaflet Js library options
   *   https://leafletjs.com/reference.html#geojson
   * @param events
   *   The events array
   *
   * @returns {*}
   */
  Drupal.Leaflet.prototype.create_json = function(json, options = [], events = []) {
    let lJSON = new L.GeoJSON();
    const self = this;

    lJSON.options.onEachFeature = function(feature, layer) {
      for (let layer_id in layer._layers) {
        for (let i in layer._layers[layer_id]._latlngs) {
        }
      }
      if (feature.properties.style) {
        layer.setStyle(feature.properties.style);
      }
      if (feature.properties.leaflet_id) {
        layer._leaflet_id = feature.properties.leaflet_id;
      }

      // Eventually add Tooltip to the lFeature.
      self.feature_bind_tooltip(layer, feature.properties);

      // Eventually add Popup to the Layer.
      self.feature_bind_popup(layer, feature.properties);

      for (const e in events) {
        let layerParam = {};
        layerParam[e] = eval(events[e]);
        layer.on(layerParam);
      }
    };

    for (const option in options) {
      if (Object.prototype.hasOwnProperty.call(options, option)) {
        lJSON.options[option] = eval(options[option]);
      }
    }

    lJSON.addData(json);
    return lJSON;
  };


  // Set Map initial map position and Zoom. Different scenarios:
  //  1)  Force the initial map center and zoom to values provided by input settings
  //  2)  Fit multiple features onto map using Leaflet's fitBounds method
  //  3)  Fit a single polygon onto map using Leaflet's fitBounds method
  //  4)  Display a single marker using the specified zoom
  //  5)  Adjust the initial zoom using zoomFiner, if specified
  //  6)  Cater for a map with no features (use input settings for Zoom and Center, if supplied)
  //
  // @NOTE: This method used by Leaflet Markecluster module (don't remove/rename)
  Drupal.Leaflet.prototype.fitBounds = function() {
    let start_zoom = this.map_settings.zoom ? this.map_settings.zoom : 12;
    // Note: this.map_settings.center might not be defined in case of Leaflet widget and Automatically locate user current position.
    let start_center = this.map_settings.center ? new L.LatLng(this.map_settings.center.lat, this.map_settings.center.lon) : new L.LatLng(0,0);

    //  Check whether the Zoom and Center are to be forced to use the input settings
    if (this.map_settings.map_position_force) {
      //  Set the Zoom and Center to values provided by the input settings
      this.lMap.setView(start_center, start_zoom);
    } else {
      if (this.bounds.length === 0) {
        //  No features - set the Zoom and Center to values provided by the input settings, if specified
        this.lMap.setView(start_center, start_zoom);
      } else {
        //  Set the Zoom and Center by using the Leaflet fitBounds function
        const bounds = new L.LatLngBounds(this.bounds);
        const fitbounds_options = this.map_settings.fitbounds_options ? JSON.parse(this.map_settings.fitbounds_options) : {};
        this.lMap.fitBounds(bounds, fitbounds_options);
        start_center = bounds.getCenter();
        start_zoom = this.lMap.getBoundsZoom(bounds);

        if (this.bounds.length === 1) {
          //  Single marker - set zoom to input settings
          this.lMap.setZoom(this.map_settings.zoom);
          start_zoom = this.map_settings.zoom;
        }
      }

      // In case of map initial position not forced, and zooFiner not null/neutral,
      // adapt the Map Zoom and the Start Zoom accordingly.
      if (this.map_settings.hasOwnProperty('zoomFiner') && parseInt(this.map_settings.zoomFiner)) {
        start_zoom += parseFloat(this.map_settings.zoomFiner);
        this.lMap.setView(start_center, start_zoom);
      }

      // Set the map start zoom and center.
      this.start_zoom = start_zoom;
      this.start_center = start_center;
    }

  };

  /**
   * Triggers a Leaflet Map Reset View action.
   *
   * @param mapid
   *   The Map identifier, to apply the rest to.
   */
  Drupal.Leaflet.prototype.map_reset = function(mapid) {
    Drupal.Leaflet[mapid].reset_view_control._resetView();
  };

  // Extend the L.Polyline to make it clustarable.
  // @see https://gis.stackexchange.com/questions/197882/is-it-possible-to-cluster-polygons-in-leaflet
  L.PolylineClusterable = L.Polyline.extend({
    _originalInitialize: L.Polyline.prototype.initialize,

    initialize: function (bounds, options) {
      this._originalInitialize(bounds, options);
      this._latlng = this.getBounds().getCenter();
    },

    getLatLng: function () {
      return this._latlng;
    },

    setLatLng: function () {}
  });

  // Extend the L.Polygon to make it clustarable.
  // @see https://gis.stackexchange.com/questions/197882/is-it-possible-to-cluster-polygons-in-leaflet
  L.PolygonClusterable = L.Polygon.extend({
    _originalInitialize: L.Polygon.prototype.initialize,

    initialize: function (bounds, options) {
      this._originalInitialize(bounds, options);
      this._latlng = this.getBounds().getCenter();
    },

    getLatLng: function () {
      return this._latlng;
    },

    setLatLng: function () {}
  });

})(jQuery, Drupal, drupalSettings, once);
