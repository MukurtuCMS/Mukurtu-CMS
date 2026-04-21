/**
 * Attach functionality for Leaflet Widget behaviours.
 */
(function ($, Drupal, drupalSettings, once) {
  Drupal.behaviors.leaflet_widget = {
    attach: function (context, settings) {
      if (!settings.leaflet) {
        return;
      }

      // For each Leaflet Map defined in the settings (in the actual document).
      $.each(settings.leaflet, function (map_id, leaflet_settings) {
        if (!map_id.includes("leaflet-map-widget")) {
          return;
        }

        // Define the leaflet-map-widget elements.
        const leaflet_elements = $(once('behaviour-leaflet-widget', '#' + map_id));
        leaflet_elements.each(function () {
          // For each element define a new Drupal.Leaflet_Widget,
          // if not already defined.
          const map_container = $(this);
          if (map_container.data('leaflet_widget') === undefined && leaflet_settings.lMap) {
            const lMap = leaflet_settings.lMap;
            map_container.data('leaflet_widget', new Drupal.Leaflet_Widget(map_container, lMap, leaflet_settings));
            // Define the global Drupal.Leaflet[mapid] object to be accessible
            // from outside.
            Drupal.Leaflet_Widget[map_id] = map_container.data('leaflet_widget');
          }
          else {
            // If we already had a widget, update map to make sure that WKT and map are synchronized.
            map_container.data('leaflet_widget').update_leaflet_widget_map();
            map_container.data('leaflet_widget').update_input_state();
          }
        });
      });
    }
  };

  Drupal.Leaflet_Widget = function (map_container, lMap, settings) {
    // A FeatureGroup is required to store editable layers
    this.map_settings = settings.map.settings;
    this.widgetsettings = settings.leaflet_widget;
    this.mapid = this.widgetsettings.map_id;
    this.drawnItems = new L.LayerGroup();
    this.map_container = map_container;
    this.container = $(map_container).parent();
    try {
      this.widgetsettings.path_style = this.map_settings.path ? JSON.parse(this.map_settings.path) : {};
    }
    catch (e) {
      this.widgetsettings.path_style = {};
    }{

    }
    this.json_selector = this.widgetsettings.jsonElement;

    if (settings.langcode && lMap.pm) {
      lMap.pm.setLang(settings.langcode);
    }

    // Initialise a property to store/manage the map in.
    this.map = undefined;

    // Initialise the Leaflet Widget Map with its features from Value element.
    this.set_leaflet_widget_map(lMap);

    // If map is initialised (or re-initialised) then use the new instance.
    this.container.on('leafletMapInit', $.proxy(function (event, _m, lMap) {
      this.set_leaflet_widget_map(lMap);
    }, this));

    // Update map whenever the input field is changed.
    this.container.on('change', this.json_selector, $.proxy(this.update_leaflet_widget_map, this));

    // Show, hide, mark read-only.
    this.update_input_state();
  };

  /**
   * Initialise the Leaflet Widget Map with its features from Value element.
   */
  Drupal.Leaflet_Widget.prototype.set_leaflet_widget_map = function (map) {
    if (map === undefined) {
      return;
    }

    this.map = map;
    map.addLayer(this.drawnItems);

    if (this.widgetsettings.scrollZoomEnabled) {
      map.on('focus', function () {
        map.scrollWheelZoom.enable();
      });
      map.on('blur', function () {
        map.scrollWheelZoom.disable();
      });
    }

    // Adjust toolbar to show defaultMarker or circleMarker.
    this.widgetsettings.toolbarSettings.drawMarker = false;
    this.widgetsettings.toolbarSettings.drawCircleMarker = false;
    if (this.widgetsettings.toolbarSettings.marker === "defaultMarker") {
      this.widgetsettings.toolbarSettings.drawMarker = true;
    } else if (this.widgetsettings.toolbarSettings.marker === "circleMarker") {
      this.widgetsettings.toolbarSettings.drawCircleMarker = true;
    }
    map.pm.addControls(this.widgetsettings.toolbarSettings);

    map.on('pm:create', function(event) {
      // Add the new Layer to the drawnItems.
      this.drawnItems.addLayer(event.layer);
      // Update GeoJSON Content text.
      this.update_text();
      // Listen to changes on the new layer.
      this.add_layer_listeners(event.layer);
    }, this);

    // Start updating the Leaflet Map.
    this.update_leaflet_widget_map();
  };

  /**
   * Update the WKT text input field.disableGlobalEditMode()
   */
  Drupal.Leaflet_Widget.prototype.update_text = function () {
    const $selector = $(this.json_selector, this.container);

    if (this.drawnItems.getLayers().length === 0) {
      $selector.val('');
    }
    else {
      const json_string = JSON.stringify(this.drawnItems.toGeoJSON());
      $selector.val(json_string);
    }
    this.container.trigger("change");
  };

  /**
   * Set visibility and readonly attribute of the input element.
   */
  Drupal.Leaflet_Widget.prototype.update_input_state = function () {
    $('.form-item.form-type-textarea, .form-item.form-type--textarea', this.container).toggle(!this.widgetsettings.inputHidden);
    $(this.json_selector, this.container).prop('readonly', this.widgetsettings.inputReadonly);
  };

  /**
   * Add/Set Listeners to the Drawn Map Layers.
   */
  Drupal.Leaflet_Widget.prototype.add_layer_listeners = function (layer) {
    // Listen to changes on the layer.
    layer.on('pm:edit', this.update_text, this);

    // Listen to changes on the layer.
    layer.on('pm:update', this.update_text, this);

    // Listen to drag events on the layer.
    layer.on('pm:dragend', this.update_text, this);

    // Listen to cut events on the layer.
    layer.on('pm:cut', function(event) {
      this.drawnItems.removeLayer(event.originalLayer);
      this.drawnItems.addLayer(event.layer);
      this.update_text();
    }, this);

    // Listen to remove events on the layer.
    layer.on('pm:remove', function(event) {
      this.drawnItems.removeLayer(event.layer);
      this.update_text();
    }, this);
  };

  /**
   * Returns the json selector value.
   */
  Drupal.Leaflet_Widget.prototype.get_json_value = function () {
    return $(this.json_selector, this.container).val();
  }

  /**
   * Update the Leaflet Widget Map from value element.
   */
  Drupal.Leaflet_Widget.prototype.update_leaflet_widget_map = function () {
    const self = this;
    const value = this.get_json_value();

    // Always clear the layers in drawnItems on map updates.
    this.drawnItems.clearLayers();

    // Apply styles to pm drawn items.
    this.map.pm.setGlobalOptions({
      pathOptions: this.widgetsettings.path_style
    });

    // Nothing to do if we don't have any data.
    if (value.length === 0) {
      // If no layer available, and the Map Center is not forced, locate the user position.
      if (this.map_settings.locate && this.map_settings.locate.automatic && !this.map_settings.map_position_force) {
        this.map.locate({setView: true, maxZoom: this.map_settings.zoom});
      }
      return;
    }

    try {
      const layerOpts = {
        style: function (feature) {
          return self.widgetsettings.path_style;
        }
      };

      // Use circleMarkers if specified.
      if (this.widgetsettings.toolbarSettings.marker === "circleMarker") {
        layerOpts.pointToLayer = function (feature, latlng) {
          return L.circleMarker(latlng);
        };
      }

      const obj = L.geoJson(JSON.parse(value), layerOpts);

      // See https://github.com/Leaflet/Leaflet.draw/issues/398
      obj.eachLayer(function(layer) {
        if (typeof layer.getLayers === "function") {
          const subLayers = layer.getLayers();
          for (let i = 0; i < subLayers.length; i++) {
            this.drawnItems.addLayer(subLayers[i]);
            this.add_layer_listeners(subLayers[i]);
          }
        }
        else {
          this.drawnItems.addLayer(layer);
          this.add_layer_listeners(layer);
        }
      }, this);

      // Pan the map to the feature
      if (this.widgetsettings.autoCenter) {
        let start_zoom;
        let start_center;

        if (obj.getBounds !== undefined && typeof obj.getBounds === 'function') {
          // For objects that have defined bounds or a way to get them
          const bounds = obj.getBounds();
          this.map.fitBounds(bounds);
          start_center = bounds.getCenter();

          // In case of Map Bounds collapsed into a Point or Map Zoom Forced,
          // use the custom Map Start Zoom (if set).
          if (this.widgetsettings.map_position.zoom &&
            (bounds.getSouthWest().distanceTo(bounds.getNorthEast()) === 0 || this.widgetsettings.map_position.force)) {
            start_zoom = this.widgetsettings.map_position.zoom;
            this.map.setZoom(start_zoom);
          }
          else {
            // Update the map start zoom and center, for correct working of Map Reset control.
            start_zoom = this.map.getBoundsZoom(bounds);
          }
        } else if (obj.getLatLng !== undefined && typeof obj.getLatLng === 'function') {
          this.map.panTo(obj.getLatLng());
          // Update the map start center, for correct working of Map Reset control.
          start_center = this.map.getCenter();
          start_zoom = this.map.getZoom();
        }

        // In case of map initial position not forced, and zoomFiner not null/neutral,
        // adapt the Map Zoom and the Start Zoom accordingly.
        if (!this.widgetsettings.map_position.force &&
            this.widgetsettings.map_position.hasOwnProperty('zoomFiner') &&
            parseInt(this.widgetsettings.map_position.zoomFiner) !== 0) {
          start_zoom += parseFloat(this.widgetsettings.map_position.zoomFiner);
          this.map.setView(start_center, start_zoom);
        }

        // Reset the StartZoom and StartCenter.
        this.reset_start_zoom_and_center(this.mapid, start_zoom, start_center);
      }
    } catch (error) {
      if (window.console) console.error(error.message);
    }
  };

  /**
   * Update the Leaflet Widget Map from value element.
   */
  Drupal.Leaflet_Widget.prototype.reset_start_zoom_and_center = function (mapid, start_zoom, start_center) {
    Drupal.Leaflet[mapid].start_zoom = start_zoom;
    Drupal.Leaflet[mapid].start_center = start_center;

    if (Drupal.Leaflet[mapid].reset_view_control) {
      Drupal.Leaflet[mapid].reset_view_control.remove();
      const map_reset_view_options = Drupal.Leaflet[mapid].map_settings.reset_map.options ?
        JSON.parse(Drupal.Leaflet[mapid].map_settings.reset_map.options) : {};
      map_reset_view_options.latlng = start_center;
      map_reset_view_options.zoom = start_zoom;
      Drupal.Leaflet[mapid].reset_view_control = L.control.resetView(map_reset_view_options)
        .addTo(Drupal.Leaflet[mapid].lMap);
    }
  };

})(jQuery, Drupal, drupalSettings, once);
